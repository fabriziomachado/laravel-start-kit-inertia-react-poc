<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Aftandilmmd\WorkflowAutomation\Enums\NodeRunStatus;
use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNodeRun;
use Aftandilmmd\WorkflowAutomation\Services\WorkflowService;
use App\Models\User;
use App\Models\WorkflowFormConversation;
use App\Services\Workflow\AiCopilotService;
use App\Services\Workflow\AiFieldExtractor;
use App\Services\Workflow\ScriptedChatService;
use App\Support\WorkflowFormFieldRules;
use App\Support\WorkflowFormProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class WorkflowFormController
{
    public function show(
        Request $request,
        string $token,
        ScriptedChatService $scriptedChat,
    ): Response {
        $payload = $this->buildStepPayload($request, $token, $scriptedChat);

        $user = $request->user();

        return Inertia::render('workflow-forms/Show', array_merge($payload, [
            'preferences' => [
                'workflow_form_renderer' => $this->rendererPreference($user),
            ],
            'workflow_form_ai_extract_available' => app(AiFieldExtractor::class)->isAvailable(),
            'workflow_form_copilot_available' => app(AiCopilotService::class)->isAvailable(),
        ]));
    }

    public function submit(Request $request, string $token, WorkflowService $workflowService): RedirectResponse
    {
        $nodeRun = $this->findWaitingFormNodeRunByToken($token);

        if ($nodeRun === null) {
            abort(404);
        }

        Gate::authorize('view', $nodeRun->workflowRun);

        $main = $nodeRun->output['main'][0] ?? [];
        $fields = $main['fields'] ?? [];
        if (! is_array($fields)) {
            $fields = [];
        }

        /** @var array<string, list<string>> $rules */
        $rules = WorkflowFormFieldRules::rulesForSubmit($fields);

        $validated = $request->validate($rules);

        $payload = $validated;
        $actor = $request->user();
        if ($actor !== null) {
            $payload['_submitted_by_id'] = (string) $actor->getKey();
            $name = mb_trim((string) $actor->name);
            if ($name !== '') {
                $payload['_submitted_by_name'] = $name;
            }
        }

        $run = $nodeRun->workflowRun;
        $run = $workflowService->resume($run, $token, $payload);
        $run->refresh();

        if ($run->isWaiting()) {
            $latest = $run->nodeRuns()
                ->where('status', NodeRunStatus::Completed)
                ->orderByDesc('id')
                ->get()
                ->first(static function (WorkflowNodeRun $nr): bool {
                    return isset($nr->output['main'][0]['resume_token']);
                });

            $nextToken = $latest?->output['main'][0]['resume_token'] ?? null;

            if (is_string($nextToken) && $nextToken !== $token) {
                return redirect()->route('workflow-forms.show', ['token' => $nextToken]);
            }
        }

        return redirect()
            ->route('flows.index')
            ->with('flows_success', 'Fluxo concluído. A execução aparece na listagem abaixo.');
    }

    /**
     * JSON variant of {@see submit()} used by the chatbot renderer so the whole flow happens
     * inside the same conversation without page reloads. Returns either the next form step
     * payload or a completion signal.
     */
    public function submitChat(
        Request $request,
        string $token,
        WorkflowService $workflowService,
        ScriptedChatService $scriptedChat,
    ): JsonResponse {
        $nodeRun = $this->findWaitingFormNodeRunByToken($token);

        if ($nodeRun === null) {
            abort(404);
        }

        Gate::authorize('view', $nodeRun->workflowRun);

        $main = $nodeRun->output['main'][0] ?? [];
        $fields = $main['fields'] ?? [];
        if (! is_array($fields)) {
            $fields = [];
        }

        /** @var array<string, list<string>> $rules */
        $rules = WorkflowFormFieldRules::rulesForSubmit($fields);
        $validated = $request->validate($rules);

        $payload = $validated;
        $actor = $request->user();
        if ($actor !== null) {
            $payload['_submitted_by_id'] = (string) $actor->getKey();
            $name = mb_trim((string) $actor->name);
            if ($name !== '') {
                $payload['_submitted_by_name'] = $name;
            }
        }

        $run = $nodeRun->workflowRun;
        $run = $workflowService->resume($run, $token, $payload);
        $run->refresh();

        $completedStep = [
            'title' => (string) ($main['title'] ?? ''),
            'submit_label' => (string) ($main['submit_label'] ?? 'Continuar'),
        ];

        if ($run->isWaiting()) {
            $latest = $run->nodeRuns()
                ->where('status', NodeRunStatus::Completed)
                ->orderByDesc('id')
                ->get()
                ->first(static function (WorkflowNodeRun $nr): bool {
                    return isset($nr->output['main'][0]['resume_token']);
                });

            $nextToken = $latest?->output['main'][0]['resume_token'] ?? null;

            if (is_string($nextToken) && $nextToken !== $token) {
                $nextPayload = $this->buildStepPayload($request, $nextToken, $scriptedChat, false);

                return response()->json([
                    'done' => false,
                    'completed_step' => $completedStep,
                    'next' => $nextPayload,
                ]);
            }
        }

        return response()->json([
            'done' => true,
            'completed_step' => $completedStep,
            'redirect_url' => route('flows.index'),
            'message' => 'Fluxo concluído. A execução aparece na listagem.',
        ]);
    }

    public function preferences(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'workflow_form_renderer' => ['required', Rule::in(['wizard', 'chatbot'])],
        ]);

        $user = $request->user();
        abort_if($user === null, 403);

        $json = DB::table((new User)->getTable())
            ->where('id', $user->getKey())
            ->value('preferences');

        $existing = [];
        if ($json !== null && $json !== '') {
            $decoded = is_string($json) ? json_decode($json, true) : $json;
            $existing = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($existing)) {
            $existing = [];
        }

        $existing['workflow_form_renderer'] = $validated['workflow_form_renderer'];
        $prefs = $existing;

        $user->forceFill(['preferences' => $prefs])->save();

        if ($request->wantsJson()) {
            return response()->json([
                'preferences' => [
                    'workflow_form_renderer' => $prefs['workflow_form_renderer'],
                ],
            ]);
        }

        return back();
    }

    public function chat(
        Request $request,
        string $token,
        ScriptedChatService $scriptedChat,
    ): JsonResponse {
        $nodeRun = $this->findWaitingFormNodeRunByToken($token);
        if ($nodeRun === null) {
            abort(404);
        }

        Gate::authorize('view', $nodeRun->workflowRun);

        $main = $nodeRun->output['main'][0] ?? [];
        $fields = is_array($main['fields'] ?? null) ? $main['fields'] : [];

        $validated = $request->validate([
            'content' => ['nullable'],
        ]);

        $conversation = WorkflowFormConversation::query()->firstOrCreate(
            [
                'workflow_run_id' => $nodeRun->workflow_run_id,
                'workflow_node_run_id' => $nodeRun->id,
            ],
            ['messages' => []],
        );

        $scriptedChat->ensureOpeningAssistant($conversation, $fields);
        $conversation->refresh();

        $result = $scriptedChat->appendUserMessage(
            $conversation,
            $fields,
            $validated['content'] ?? null,
        );

        if (! $result['ok']) {
            return response()->json(['errors' => $result['errors']], 422);
        }

        return response()->json([
            'messages' => $result['messages'],
            'ready_for_submit' => $scriptedChat->isReadyForSubmit($result['messages']),
            'draft_values' => $scriptedChat->draftValuesFromMessages($fields, $result['messages']),
        ]);
    }

    public function edit(
        Request $request,
        string $token,
        ScriptedChatService $scriptedChat,
    ): JsonResponse {
        $nodeRun = $this->findWaitingFormNodeRunByToken($token);
        if ($nodeRun === null) {
            abort(404);
        }

        Gate::authorize('view', $nodeRun->workflowRun);

        $validated = $request->validate([
            'field_key' => ['required', 'string'],
            'content' => ['nullable'],
            'workflow_node_run_id' => ['nullable', 'integer'],
        ]);

        $run = $nodeRun->workflowRun;
        $workflow = $run->workflow()->with(['nodes', 'edges'])->firstOrFail();

        $currentConversation = WorkflowFormConversation::query()->firstOrCreate(
            [
                'workflow_run_id' => $nodeRun->workflow_run_id,
                'workflow_node_run_id' => $nodeRun->id,
            ],
            ['messages' => []],
        );

        $targetConversation = $currentConversation;
        if (isset($validated['workflow_node_run_id'])) {
            $candidate = WorkflowFormConversation::query()
                ->where('workflow_run_id', $run->id)
                ->where('workflow_node_run_id', (int) $validated['workflow_node_run_id'])
                ->first();
            if ($candidate === null) {
                return response()->json(['message' => 'Conversa desta etapa não encontrada.'], 422);
            }
            $targetConversation = $candidate;
        }

        $targetNodeRun = $targetConversation->workflowNodeRun()->firstOrFail();
        $targetMain = $targetNodeRun->output['main'][0] ?? [];
        $targetFields = is_array($targetMain['fields'] ?? null) ? $targetMain['fields'] : [];

        $result = $scriptedChat->replaceUserMessage(
            $targetConversation,
            $targetFields,
            $validated['field_key'],
            $validated['content'] ?? null,
        );

        if (! $result['ok']) {
            return response()->json(['errors' => $result['errors']], 422);
        }

        $currentConversation->refresh();

        $currentMain = $nodeRun->output['main'][0] ?? [];
        $currentFields = is_array($currentMain['fields'] ?? null) ? $currentMain['fields'] : [];
        $currentMessages = is_array($currentConversation->messages) ? $currentConversation->messages : [];

        $targetConversation->refresh();
        $targetMessages = is_array($targetConversation->messages) ? $targetConversation->messages : [];

        $draftValues = array_merge(
            $scriptedChat->draftValuesFromMessages($targetFields, $targetMessages),
            $scriptedChat->draftValuesFromMessages($currentFields, $currentMessages),
        );

        $cumulative = WorkflowFormProgress::cumulativeFormChatMessages($run, $workflow, $nodeRun, $currentConversation);

        return response()->json([
            'messages' => $currentMessages,
            'cumulative_messages' => $cumulative,
            'ready_for_submit' => $scriptedChat->isReadyForSubmit($currentMessages),
            'draft_values' => $draftValues,
        ]);
    }

    public function aiExtract(
        Request $request,
        string $token,
        AiFieldExtractor $extractor,
    ): JsonResponse {
        $nodeRun = $this->findWaitingFormNodeRunByToken($token);
        if ($nodeRun === null) {
            abort(404);
        }

        Gate::authorize('view', $nodeRun->workflowRun);

        if (! $extractor->isAvailable()) {
            return response()->json(['message' => 'Extração por IA não está configurada.'], 503);
        }

        $main = $nodeRun->output['main'][0] ?? [];
        $fields = is_array($main['fields'] ?? null) ? $main['fields'] : [];

        $validated = $request->validate([
            'free_text' => ['required', 'string', 'max:16000'],
        ]);

        try {
            $values = $extractor->extract($fields, $validated['free_text']);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Falha ao extrair dados. Tenta de novo.'], 502);
        }

        return response()->json(['values' => $values]);
    }

    public function aiCopilot(
        Request $request,
        string $token,
        AiCopilotService $copilot,
    ): JsonResponse {
        $nodeRun = $this->findWaitingFormNodeRunByToken($token);
        if ($nodeRun === null) {
            abort(404);
        }

        $run = $nodeRun->workflowRun;
        Gate::authorize('view', $run);

        if (! $copilot->isAvailable()) {
            return response()->json(['message' => 'Assistente IA não está configurado.'], 503);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        try {
            $reply = $copilot->answer($run, $validated['message']);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Falha ao obter resposta. Tenta de novo.'], 502);
        }

        return response()->json(['reply' => $reply]);
    }

    /**
     * Build the payload describing the current form step (token, step, prefill, progress, conversation).
     *
     * @return array<string, mixed>
     */
    /**
     * @param  bool  $cumulativeChatMessages  Se true (página Inertia), devolve o histórico de chat
     *                                        de todas as etapas de formulário até à atual. Se false
     *                                        (payload JSON ao avançar no chat), só o segmento da etapa atual.
     */
    private function buildStepPayload(
        Request $request,
        string $token,
        ScriptedChatService $scriptedChat,
        bool $cumulativeChatMessages = true,
    ): array {
        $nodeRun = $this->findWaitingFormNodeRunByToken($token);

        if ($nodeRun === null) {
            abort(404);
        }

        $run = $nodeRun->workflowRun;
        Gate::authorize('view', $run);

        $main = $nodeRun->output['main'][0] ?? [];
        $fields = is_array($main['fields'] ?? null) ? $main['fields'] : [];

        $step = [
            'title' => (string) ($main['title'] ?? ''),
            'description' => $main['description'] ?? null,
            'submit_label' => (string) ($main['submit_label'] ?? 'Continuar'),
            'fields' => $fields,
        ];

        $run->refresh();
        $workflow = $run->workflow()->with(['nodes', 'edges'])->firstOrFail();

        $viewerName = ($u = $request->user()) !== null ? mb_trim((string) $u->name) : '';
        $viewerDisplayName = $viewerName !== '' ? $viewerName : null;

        $conversation = WorkflowFormConversation::query()->firstOrCreate(
            [
                'workflow_run_id' => $nodeRun->workflow_run_id,
                'workflow_node_run_id' => $nodeRun->id,
            ],
            ['messages' => []],
        );

        $scriptedChat->ensureOpeningAssistant($conversation, $fields);
        $conversation->refresh();

        $conversationMessages = is_array($conversation->messages ?? null)
            ? $conversation->messages
            : [];

        return [
            'token' => $token,
            'step' => $step,
            'run_id' => $nodeRun->workflow_run_id,
            'prefill' => WorkflowFormProgress::prefillForFields(
                $run,
                $nodeRun,
                $fields,
                $conversationMessages,
                $scriptedChat,
            ),
            'previous_token' => WorkflowFormProgress::previousFormResumeToken($run, $workflow, $nodeRun),
            'progress' => [
                'workflow_name' => $workflow->name,
                'workflow_description' => is_string($workflow->description) && mb_trim($workflow->description) !== ''
                    ? mb_trim($workflow->description)
                    : null,
                'steps' => WorkflowFormProgress::timeline($run, $workflow, $nodeRun, $viewerDisplayName),
            ],
            'conversation' => [
                'id' => $conversation->id,
                'messages' => $cumulativeChatMessages
                    ? WorkflowFormProgress::cumulativeFormChatMessages($run, $workflow, $nodeRun, $conversation)
                    : (is_array($conversation->messages) ? $conversation->messages : []),
            ],
        ];
    }

    private function findWaitingFormNodeRunByToken(string $token): ?WorkflowNodeRun
    {
        return WorkflowNodeRun::query()
            ->where('status', NodeRunStatus::Completed)
            ->with('workflowRun')
            ->get()
            ->first(static function (WorkflowNodeRun $run) use ($token): bool {
                if (($run->output['main'][0]['resume_token'] ?? null) !== $token) {
                    return false;
                }

                return $run->workflowRun?->status === RunStatus::Waiting;
            });
    }

    private function rendererPreference(?User $user): string
    {
        if ($user === null) {
            return 'wizard';
        }

        $json = DB::table((new User)->getTable())
            ->where('id', $user->getKey())
            ->value('preferences');

        if ($json === null || $json === '') {
            return 'wizard';
        }

        $decoded = is_string($json) ? json_decode($json, true) : $json;
        if (! is_array($decoded)) {
            return 'wizard';
        }

        $v = $decoded['workflow_form_renderer'] ?? null;

        return $v === 'chatbot' ? 'chatbot' : 'wizard';
    }
}
