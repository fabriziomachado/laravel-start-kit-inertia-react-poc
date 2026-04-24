<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use App\Models\WorkflowFormConversation;
use App\Support\WorkflowFormFieldRules;
use Illuminate\Support\Facades\Validator;

final class ScriptedChatService
{
    private const string META_EXPECTING = 'expecting_field';

    private const string META_PHASE = 'phase';

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    public function ensureOpeningAssistant(WorkflowFormConversation $conversation, array $fields): void
    {
        $messages = $conversation->messages ?? [];
        if ($messages !== []) {
            return;
        }

        $first = $this->firstField($fields);
        if ($first === null) {
            return;
        }

        $messages[] = $this->assistantExpectingField($first);
        $conversation->messages = $messages;
        $conversation->save();
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array{ok: true, messages: list<array<string, mixed>>}|array{ok: false, errors: array<string, list<string>>}
     */
    public function appendUserMessage(WorkflowFormConversation $conversation, array $fields, mixed $rawContent): array
    {
        $messages = $conversation->messages ?? [];
        $expectingKey = $this->currentExpectingFieldKey($messages);
        if ($expectingKey === null) {
            return ['ok' => false, 'errors' => ['chat' => ['Não há pergunta pendente.']]];
        }

        $field = $this->fieldByKey($fields, $expectingKey);
        if ($field === null) {
            return ['ok' => false, 'errors' => ['chat' => ['Campo inválido.']]];
        }

        $normalized = $this->normalizeContent($field, $rawContent);
        $rules = WorkflowFormFieldRules::rulesForSingleField($field);
        $validator = Validator::make([$expectingKey => $normalized], [$expectingKey => $rules]);
        if ($validator->fails()) {
            return ['ok' => false, 'errors' => $validator->errors()->toArray()];
        }

        $validated = $validator->validated();
        $value = $validated[$expectingKey] ?? $normalized;

        $messages[] = [
            'role' => 'user',
            'content' => $this->userMessageContent($field, $value),
            'meta' => array_merge([
                self::META_EXPECTING => $expectingKey,
                'at' => now()->toIso8601String(),
                'field_type' => (string) ($field['type'] ?? 'string'),
                'field_label' => (string) ($field['label'] ?? $field['key'] ?? $expectingKey),
                'workflow_node_run_id' => (int) $conversation->workflow_node_run_id,
            ], $this->fieldSchemaMeta($field)),
        ];

        $nextField = $this->nextFieldAfter($fields, $expectingKey);
        if ($nextField !== null) {
            $messages[] = $this->assistantExpectingField($nextField);
        } else {
            // Marca a etapa como completa na conversa; a UI avança sem pedir confirmação explícita.
            $messages[] = [
                'role' => 'assistant',
                'content' => '',
                'meta' => [
                    self::META_PHASE => 'ready_for_submit',
                    'at' => now()->toIso8601String(),
                ],
            ];
        }

        $conversation->messages = $messages;
        $conversation->save();

        return ['ok' => true, 'messages' => $messages];
    }

    /**
     * Substitui o conteúdo da resposta do utilizador para um campo já respondido,
     * mantendo intacta a estrutura da conversa (perguntas e demais respostas).
     *
     * @param  list<array<string, mixed>>  $fields
     * @return array{ok: true, messages: list<array<string, mixed>>}|array{ok: false, errors: array<string, list<string>>}
     */
    public function replaceUserMessage(
        WorkflowFormConversation $conversation,
        array $fields,
        string $fieldKey,
        mixed $rawContent,
    ): array {
        $field = $this->fieldByKey($fields, $fieldKey);
        if ($field === null) {
            return ['ok' => false, 'errors' => ['chat' => ['Campo inválido.']]];
        }

        $normalized = $this->normalizeContent($field, $rawContent);
        $rules = WorkflowFormFieldRules::rulesForSingleField($field);
        $validator = Validator::make([$fieldKey => $normalized], [$fieldKey => $rules]);
        if ($validator->fails()) {
            return ['ok' => false, 'errors' => $validator->errors()->toArray()];
        }

        $validated = $validator->validated();
        $value = $validated[$fieldKey] ?? $normalized;

        $messages = $conversation->messages ?? [];
        $targetIdx = null;
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            $row = $messages[$i] ?? null;
            if (! is_array($row) || ($row['role'] ?? null) !== 'user') {
                continue;
            }
            $meta = $row['meta'] ?? null;
            if (is_array($meta) && ($meta[self::META_EXPECTING] ?? null) === $fieldKey) {
                $targetIdx = $i;
                break;
            }
        }

        if ($targetIdx === null) {
            return ['ok' => false, 'errors' => ['chat' => ['Resposta não encontrada para edição.']]];
        }

        $messages[$targetIdx]['content'] = $this->userMessageContent($field, $value);
        $meta = is_array($messages[$targetIdx]['meta'] ?? null) ? $messages[$targetIdx]['meta'] : [];
        $meta[self::META_EXPECTING] = $fieldKey;
        $meta['at'] = now()->toIso8601String();
        $meta['edited'] = true;
        $meta['field_type'] = (string) ($field['type'] ?? 'string');
        $meta['field_label'] = (string) ($field['label'] ?? $field['key'] ?? $fieldKey);
        $meta['workflow_node_run_id'] = (int) $conversation->workflow_node_run_id;
        $messages[$targetIdx]['meta'] = array_merge($meta, $this->fieldSchemaMeta($field));

        $conversation->messages = $messages;
        $conversation->save();

        return ['ok' => true, 'messages' => $messages];
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     */
    public function isReadyForSubmit(array $messages): bool
    {
        foreach (array_reverse($messages) as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (($row['role'] ?? null) !== 'assistant') {
                continue;
            }
            $meta = $row['meta'] ?? null;
            if (is_array($meta) && ($meta[self::META_PHASE] ?? null) === 'ready_for_submit') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    public function draftValuesFromMessages(array $fields, array $messages): array
    {
        $out = [];
        foreach ($messages as $row) {
            if (! is_array($row) || ($row['role'] ?? null) !== 'user') {
                continue;
            }
            $meta = $row['meta'] ?? null;
            if (! is_array($meta) || ! isset($meta[self::META_EXPECTING])) {
                continue;
            }
            $key = (string) $meta[self::META_EXPECTING];
            $field = $this->fieldByKey($fields, $key);
            if ($field === null) {
                continue;
            }
            $parsed = $this->parseValueFromUserContent($field, (string) ($row['content'] ?? ''));
            $out[$key] = $parsed;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    private function firstField(array $fields): ?array
    {
        foreach ($fields as $field) {
            if (is_array($field) && isset($field['key'])) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    private function nextFieldAfter(array $fields, string $currentKey): ?array
    {
        $seen = false;
        foreach ($fields as $field) {
            if (! is_array($field) || ! isset($field['key'])) {
                continue;
            }
            if ($seen) {
                return $field;
            }
            if ((string) $field['key'] === $currentKey) {
                $seen = true;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, mixed>|null
     */
    private function fieldByKey(array $fields, string $key): ?array
    {
        foreach ($fields as $field) {
            if (is_array($field) && isset($field['key']) && (string) $field['key'] === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     */
    private function currentExpectingFieldKey(array $messages): ?string
    {
        foreach (array_reverse($messages) as $row) {
            if (! is_array($row) || ($row['role'] ?? null) !== 'assistant') {
                continue;
            }
            $meta = $row['meta'] ?? null;
            if (! is_array($meta)) {
                continue;
            }
            if (isset($meta[self::META_EXPECTING]) && is_string($meta[self::META_EXPECTING])) {
                return $meta[self::META_EXPECTING];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array{role: string, content: string, meta: array<string, string>}
     */
    private function assistantExpectingField(array $field): array
    {
        $key = (string) $field['key'];

        return [
            'role' => 'assistant',
            'content' => $this->questionText($field),
            'meta' => [
                self::META_EXPECTING => $key,
                'at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function questionText(array $field): string
    {
        $hints = $field['ui_hints'] ?? null;
        if (is_array($hints) && isset($hints['ask']) && is_string($hints['ask']) && mb_trim($hints['ask']) !== '') {
            return $this->asQuestion(mb_trim($hints['ask']));
        }
        if (isset($field['ask']) && is_string($field['ask']) && mb_trim($field['ask']) !== '') {
            return $this->asQuestion(mb_trim($field['ask']));
        }

        return $this->asQuestion((string) ($field['label'] ?? $field['key']));
    }

    /**
     * Ensure the assistant bubble ends with a question mark so the UX feels like a conversation.
     */
    private function asQuestion(string $text): string
    {
        $text = mb_trim($text);
        if ($text === '') {
            return $text;
        }

        $last = mb_substr($text, -1);
        if (in_array($last, ['?', '.', '!', ':'], true)) {
            if ($last === '.' || $last === ':') {
                return mb_substr($text, 0, -1).'?';
            }

            return $text;
        }

        return $text.'?';
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function normalizeContent(array $field, mixed $rawContent): mixed
    {
        $type = (string) ($field['type'] ?? 'string');
        if ($type === 'boolean') {
            if (is_bool($rawContent)) {
                return $rawContent;
            }
            if (is_string($rawContent)) {
                $s = mb_strtolower(mb_trim($rawContent));

                return in_array($s, ['1', 'true', 'sim', 'yes', 'on'], true);
            }

            return (bool) $rawContent;
        }

        if ($type === 'number' && is_string($rawContent) && $rawContent === '') {
            return '';
        }

        return $rawContent;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function userMessageContent(array $field, mixed $value): string
    {
        $type = (string) ($field['type'] ?? 'string');
        if ($type === 'boolean') {
            return $value ? 'Sim' : 'Não';
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function parseValueFromUserContent(array $field, string $content): mixed
    {
        $type = (string) ($field['type'] ?? 'string');
        if ($type === 'boolean') {
            $s = mb_strtolower(mb_trim($content));

            return in_array($s, ['1', 'true', 'sim', 'yes'], true);
        }
        if ($type === 'number') {
            if ($content === '') {
                return '';
            }

            return 0 + (float) $content;
        }

        return $content;
    }

    /**
     * CSV de opções (select) ou lista de cartões (choice_cards) para a UI poder editar
     * respostas no histórico cumulativo sem depender da definição da etapa atual.
     *
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private function fieldSchemaMeta(array $field): array
    {
        $type = (string) ($field['type'] ?? 'string');
        if ($type === 'select') {
            $opt = $field['options'] ?? '';

            return [
                'field_options' => is_string($opt) ? $opt : (is_scalar($opt) ? (string) $opt : ''),
            ];
        }
        if ($type === 'choice_cards') {
            $raw = $field['choices'] ?? [];

            return [
                'field_choices' => $this->normalizeChoicesForMeta(is_array($raw) ? $raw : []),
            ];
        }

        return [];
    }

    /**
     * @param  list<mixed>  $raw
     * @return list<array<string, mixed>>
     */
    private function normalizeChoicesForMeta(array $raw): array
    {
        $out = [];
        foreach ($raw as $c) {
            if (! is_array($c)) {
                continue;
            }
            $value = (string) ($c['value'] ?? '');
            if ($value === '') {
                continue;
            }
            $row = [
                'value' => $value,
                'label' => (string) ($c['label'] ?? $value),
            ];
            if (isset($c['description']) && is_string($c['description'])) {
                $row['description'] = $c['description'];
            }
            if (isset($c['icon']) && is_string($c['icon'])) {
                $row['icon'] = $c['icon'];
            }
            $out[] = $row;
        }

        return $out;
    }
}
