<?php

declare(strict_types=1);

namespace App\Support;

use Aftandilmmd\WorkflowAutomation\Enums\NodeRunStatus;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNodeRun;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use App\Models\User;
use Illuminate\Support\Collection;

final class WorkflowFormProgress
{
    /**
     * Linha do tempo do fluxo para o ecrã de formulário (etapa atual + concluídas + pendentes).
     *
     * @return list<array{
     *     node_id: int,
     *     label: string,
     *     node_key: string,
     *     state: 'completed'|'current'|'pending',
     *     completed_at: string|null,
     *     summary_lines: list<string>,
     *     description: string|null,
     *     actor_name: string|null
     * }>
     */
    public static function timeline(
        WorkflowRun $run,
        Workflow $workflow,
        WorkflowNodeRun $activeFormNodeRun,
        ?string $viewerDisplayName = null,
    ): array {
        $run->loadMissing(['nodeRuns']);
        $workflow->loadMissing(['nodes', 'edges']);

        $ordered = self::orderedNodes($workflow);
        if ($ordered->isEmpty()) {
            return [];
        }

        $currentNodeId = $activeFormNodeRun->node_id;
        $currentIndex = $ordered->search(fn (WorkflowNode $n): bool => $n->id === $currentNodeId);
        if ($currentIndex === false) {
            $currentIndex = 0;
        }

        $context = $run->context ?? [];
        $nodeRunsByNodeId = $run->nodeRuns->keyBy('node_id');

        $steps = [];
        foreach ($ordered->values() as $index => $node) {
            $state = match (true) {
                $index < $currentIndex => 'completed',
                $index === $currentIndex => 'current',
                default => 'pending',
            };

            $nr = $nodeRunsByNodeId->get($node->id);
            $completedAt = ($nr instanceof WorkflowNodeRun && $nr->status === NodeRunStatus::Completed)
                ? $nr->executed_at?->toIso8601String()
                : null;

            $steps[] = [
                'node_id' => $node->id,
                'label' => self::nodeLabel($node),
                'node_key' => $node->node_key,
                'state' => $state,
                'completed_at' => $completedAt,
                'summary_lines' => self::summaryForNode($node, $context, $state),
                'description' => self::nodeStepDescription($node),
                'actor_name' => self::stepActorName($node, $context, $state, $viewerDisplayName),
            ];
        }

        return $steps;
    }

    /**
     * Token do passo form_step imediatamente anterior na ordem do grafo (porta main),
     * para o utilizador voltar ao URL desse passo sem alterar o estado da execução.
     */
    public static function previousFormResumeToken(WorkflowRun $run, Workflow $workflow, WorkflowNodeRun $activeFormNodeRun): ?string
    {
        $run->loadMissing(['nodeRuns']);
        $workflow->loadMissing(['nodes', 'edges']);

        $ordered = self::orderedNodes($workflow);
        if ($ordered->isEmpty()) {
            return null;
        }

        $currentIndex = $ordered->search(fn (WorkflowNode $n): bool => $n->id === $activeFormNodeRun->node_id);
        if ($currentIndex === false) {
            return null;
        }

        $sequence = $ordered->values();

        for ($i = (int) $currentIndex - 1; $i >= 0; $i--) {
            $node = $sequence->get($i);
            if (! $node instanceof WorkflowNode || $node->node_key !== 'form_step') {
                continue;
            }

            return self::resumeTokenForFormNode($run, $node->id);
        }

        return null;
    }

    /**
     * Valores já guardados em workflow_runs.context para repor campos ao voltar atrás.
     *
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    public static function prefillForFields(WorkflowRun $run, WorkflowNodeRun $formNodeRun, array $fields): array
    {
        $nodeId = $formNodeRun->node_id;
        $saved = data_get($run->context, "{$nodeId}.main.0")
            ?? data_get($run->context, (string) $nodeId.'.main.0');

        if (! is_array($saved)) {
            return [];
        }

        $prefill = [];
        foreach ($fields as $field) {
            if (! is_array($field) || ! isset($field['key'])) {
                continue;
            }
            $key = (string) $field['key'];
            if (! array_key_exists($key, $saved)) {
                continue;
            }
            $prefill[$key] = $saved[$key];
        }

        return $prefill;
    }

    /**
     * Resumo só de leitura de uma execução concluída (dados em {@see WorkflowRun::$context} por nó).
     *
     * @return list<array{heading: string, lines: list<string>}>
     */
    public static function completedRunReadOnlySections(WorkflowRun $run, Workflow $workflow): array
    {
        $workflow->loadMissing(['nodes', 'edges']);

        $ordered = self::orderedNodes($workflow);
        if ($ordered->isEmpty()) {
            return [];
        }

        $context = $run->context ?? [];
        $sections = [];

        foreach ($ordered as $node) {
            $nodeIdKey = (string) $node->id;

            if ($node->node_key === 'email_approval') {
                $decisionPayload = self::emailApprovalDecisionPayload($context, $node->id);
                if ($decisionPayload === null) {
                    continue;
                }

                $sections[] = [
                    'heading' => self::nodeLabel($node),
                    'lines' => self::emailApprovalReadOnlyLines($decisionPayload),
                ];

                continue;
            }

            $mainFirst = data_get($context, "{$nodeIdKey}.main.0");

            if (! is_array($mainFirst)) {
                continue;
            }

            if ($node->node_key === 'form_step') {
                $lines = self::submittedFieldLines($node, $mainFirst);
                if ($lines !== []) {
                    $sections[] = [
                        'heading' => self::nodeLabel($node),
                        'lines' => $lines,
                    ];
                }

                continue;
            }

            if ($node->node_key === 'set_fields') {
                $lines = [];
                foreach ($mainFirst as $k => $v) {
                    if (! is_string($k) || is_array($v)) {
                        continue;
                    }
                    $lines[] = self::formatPair($k, $v);
                }
                $lines = array_slice($lines, 0, 24);
                if ($lines !== []) {
                    $sections[] = [
                        'heading' => self::nodeLabel($node),
                        'lines' => $lines,
                    ];
                }

                continue;
            }

            if ($node->node_key === 'send_mail') {
                $sections[] = [
                    'heading' => self::nodeLabel($node),
                    'lines' => self::sendMailReadOnlyLines($mainFirst),
                ];

                continue;
            }

            if ($node->type === NodeType::Trigger) {
                $lines = [];
                foreach ($mainFirst as $k => $v) {
                    if (is_array($v)) {
                        continue;
                    }
                    $lines[] = self::formatPair((string) $k, $v);
                }
                if ($lines !== []) {
                    $sections[] = [
                        'heading' => self::nodeLabel($node),
                        'lines' => $lines,
                    ];
                }
            }
        }

        return $sections;
    }

    private static function resumeTokenForFormNode(WorkflowRun $run, int $nodeId): ?string
    {
        $run->loadMissing(['nodeRuns']);

        $candidates = $run->nodeRuns
            ->where('node_id', $nodeId)
            ->where('status', NodeRunStatus::Completed)
            ->sortByDesc('id');

        foreach ($candidates as $nr) {
            if (! $nr instanceof WorkflowNodeRun) {
                continue;
            }
            $t = $nr->output['main'][0]['resume_token'] ?? null;
            if (is_string($t) && $t !== '') {
                return $t;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, WorkflowNode>
     */
    private static function orderedNodes(Workflow $workflow): Collection
    {
        $nodes = $workflow->nodes->keyBy('id');
        $trigger = $workflow->nodes->first(static fn (WorkflowNode $n): bool => $n->type === NodeType::Trigger);

        if ($trigger === null) {
            return collect();
        }

        $edges = $workflow->edges->sortBy('id');

        $ordered = collect();
        $queue = [$trigger->id];
        $seen = [];

        while ($queue !== []) {
            $id = (int) array_shift($queue);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;

            $node = $nodes->get($id);
            if (! $node instanceof WorkflowNode) {
                continue;
            }

            $ordered->push($node);

            foreach ($edges->where('source_node_id', $id) as $edge) {
                $queue[] = (int) $edge->target_node_id;
            }
        }

        return $ordered;
    }

    private static function nodeLabel(WorkflowNode $node): string
    {
        if ($node->node_key === 'form_step') {
            $title = $node->config['title'] ?? null;

            if (is_string($title) && $title !== '') {
                return $title;
            }

            return self::nodeFallbackLabel($node);
        }

        if ($node->type === NodeType::Trigger) {
            return 'Início';
        }

        return self::nodeFallbackLabel($node);
    }

    private static function nodeFallbackLabel(WorkflowNode $node): string
    {
        $name = $node->name;
        if (is_string($name) && mb_trim($name) !== '') {
            return mb_trim($name);
        }

        $key = $node->node_key;

        return is_string($key) && $key !== '' ? $key : 'Passo';
    }

    private static function nodeStepDescription(WorkflowNode $node): ?string
    {
        $raw = $node->config['description'] ?? null;
        if (! is_string($raw)) {
            return null;
        }

        $trimmed = mb_trim($raw);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function stepActorName(
        WorkflowNode $node,
        array $context,
        string $state,
        ?string $viewerDisplayName,
    ): ?string {
        $mainFirst = self::contextMainFirst($context, $node->id);
        if (is_array($mainFirst)) {
            $submitted = $mainFirst['_submitted_by_name'] ?? null;
            if (is_string($submitted) && mb_trim($submitted) !== '') {
                return mb_trim($submitted);
            }

            $id = $mainFirst['_submitted_by_id'] ?? null;
            if ($id !== null && $id !== '') {
                $resolved = User::query()->find($id)?->name;
                if (is_string($resolved) && mb_trim($resolved) !== '') {
                    return mb_trim($resolved);
                }
            }
        }

        if ($state === 'current' && $node->node_key === 'form_step') {
            if (is_string($viewerDisplayName) && mb_trim($viewerDisplayName) !== '') {
                return mb_trim($viewerDisplayName);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function contextMainFirst(array $context, int $nodeId): mixed
    {
        return data_get($context, "{$nodeId}.main.0")
            ?? data_get($context, (string) $nodeId.'.main.0');
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    private static function summaryForNode(WorkflowNode $node, array $context, string $state): array
    {
        $mainFirst = self::contextMainFirst($context, $node->id);

        if ($node->node_key === 'form_step') {
            if ($state === 'completed' && is_array($mainFirst)) {
                return self::submittedFieldLines($node, $mainFirst);
            }

            return [];
        }

        if ($node->type === NodeType::Trigger && is_array($mainFirst)) {
            $lines = [];
            foreach ($mainFirst as $k => $v) {
                if (is_array($v)) {
                    continue;
                }
                $lines[] = self::formatPair((string) $k, $v);
            }

            return $lines;
        }

        if ($node->node_key === 'set_fields' && is_array($mainFirst)) {
            $lines = [];
            foreach ($mainFirst as $k => $v) {
                if (is_array($v)) {
                    continue;
                }
                $lines[] = self::formatPair((string) $k, $v);
            }

            return array_slice($lines, 0, 12);
        }

        if ($node->node_key === 'send_mail' && is_array($mainFirst)) {
            return self::sendMailReadOnlyLines($mainFirst);
        }

        if ($node->node_key === 'email_approval') {
            $decisionPayload = self::emailApprovalDecisionPayload($context, $node->id);
            if ($decisionPayload !== null) {
                return self::emailApprovalReadOnlyLines($decisionPayload);
            }
        }

        return [];
    }

    /**
     * Payload gravado em workflow_runs.context para uma execução de email_approval.
     * O engine guarda o resumePayload em "{nodeId}.{approved|rejected}.0" quando
     * o {@see \Aftandilmmd\WorkflowAutomation\Jobs\ResumeWorkflowJob} retoma a
     * execução.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private static function emailApprovalDecisionPayload(array $context, int $nodeId): ?array
    {
        foreach (['approved', 'rejected'] as $port) {
            $payload = data_get($context, "{$nodeId}.{$port}.0")
                ?? data_get($context, (string) $nodeId.'.'.$port.'.0');

            if (is_array($payload) && $payload !== []) {
                return $payload;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private static function emailApprovalReadOnlyLines(array $payload): array
    {
        $decision = is_string($payload['decision'] ?? null) ? $payload['decision'] : null;
        $lines = [
            'Decisão: '.match ($decision) {
                'approve' => 'Aprovado',
                'reject' => 'Rejeitado',
                default => '—',
            },
        ];

        $comment = $payload['comment'] ?? null;
        if (is_string($comment) && mb_trim($comment) !== '') {
            $lines[] = self::formatPair('Comentário', mb_trim($comment));
        }

        $decidedAt = $payload['decided_at'] ?? null;
        if (is_string($decidedAt) && $decidedAt !== '') {
            try {
                $decidedAt = \Illuminate\Support\Carbon::parse($decidedAt)
                    ->timezone(config('app.timezone', 'UTC'))
                    ->format('d/m/Y H:i:s');
            } catch (\Throwable) {
            }
            $lines[] = self::formatPair('Decidida em', $decidedAt);
        }

        $decidedBy = $payload['decided_by_email'] ?? null;
        if (is_string($decidedBy) && mb_trim($decidedBy) !== '') {
            $lines[] = self::formatPair('Decidida por', mb_trim($decidedBy));
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $mainFirst
     * @return list<string>
     */
    private static function sendMailReadOnlyLines(array $mainFirst): array
    {
        $sent = ($mainFirst['mail_sent'] ?? false) === true;
        $lines = [
            $sent ? 'Estado: enviado' : 'Estado: não confirmado como enviado',
        ];

        foreach (['subject' => 'Assunto', 'to' => 'Para', 'mailable_to' => 'Para', 'mailable_class' => 'Mailable'] as $key => $ptLabel) {
            $v = $mainFirst[$key] ?? null;
            if (is_string($v) && $v !== '') {
                $lines[] = self::formatPair($ptLabel, $v);
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $mainFirst
     * @return list<string>
     */
    private static function submittedFieldLines(WorkflowNode $node, array $mainFirst): array
    {
        $ignore = ['resume_token', 'title', 'description', 'submit_label', 'fields'];
        /** @var array<string, string> $labels */
        $labels = [];
        $fields = $node->config['fields'] ?? [];
        if (is_array($fields)) {
            foreach ($fields as $f) {
                if (is_array($f) && isset($f['key'], $f['label'])) {
                    $labels[(string) $f['key']] = (string) $f['label'];
                }
            }
        }

        $lines = [];
        foreach ($mainFirst as $k => $v) {
            if (! is_string($k) || str_starts_with($k, '_') || in_array($k, $ignore, true)) {
                continue;
            }
            if (is_array($v)) {
                continue;
            }
            $label = $labels[$k] ?? $k;
            $display = self::humanizeSubmittedFieldValue($fields, $k, $v);
            $lines[] = self::formatPair($label, $display);
        }

        return $lines;
    }

    /**
     * @param  array<int, mixed>  $fields
     */
    private static function humanizeSubmittedFieldValue(array $fields, string $key, mixed $value): mixed
    {
        foreach ($fields as $f) {
            if (! is_array($f) || (string) ($f['key'] ?? '') !== $key) {
                continue;
            }
            if (($f['type'] ?? '') !== 'choice_cards' || ! is_string($value)) {
                return $value;
            }
            $choices = $f['choices'] ?? [];
            if (! is_array($choices)) {
                return $value;
            }
            foreach ($choices as $c) {
                if (is_array($c) && (string) ($c['value'] ?? '') === $value) {
                    return (string) ($c['label'] ?? $value);
                }
            }

            return $value;
        }

        return $value;
    }

    private static function formatPair(string $label, mixed $value): string
    {
        $val = match (true) {
            is_bool($value) => $value ? 'Sim' : 'Não',
            $value === null || $value === '' => '—',
            default => (string) $value,
        };

        if (mb_strlen($val) > 120) {
            $val = mb_substr($val, 0, 117).'…';
        }

        return "{$label}: {$val}";
    }
}
