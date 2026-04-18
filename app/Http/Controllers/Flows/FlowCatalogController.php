<?php

declare(strict_types=1);

namespace App\Http\Controllers\Flows;

use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use App\Flows\FlowRunStarter;
use App\Flows\WorkflowStarterPayload;
use App\Models\User;
use App\Support\WorkflowFormProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class FlowCatalogController
{
    public function __construct(
        private FlowRunStarter $flowRunStarter,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $workflows = Workflow::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        if ($workflows->isEmpty()) {
            return redirect()
                ->route('dashboard')
                ->with('flows_error', 'Não há fluxos disponíveis. Contacte o administrador.');
        }

        $currentUserId = (string) $request->user()->id;
        $isAdmin = (bool) $request->user()->is_admin;

        $activeWorkflowIds = $workflows->pluck('id')->all();

        $runsCollection = WorkflowRun::query()
            ->whereIn('workflow_id', $activeWorkflowIds)
            ->with('workflow')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->filter(function (WorkflowRun $run) use ($currentUserId, $isAdmin): bool {
                if ($isAdmin) {
                    return true;
                }

                $starterId = WorkflowStarterPayload::starterUserId($run);

                return $starterId !== null && $starterId === $currentUserId;
            })
            ->take(50)
            ->values();

        $starterIds = $runsCollection
            ->map(static fn (WorkflowRun $run): ?string => WorkflowStarterPayload::starterUserId($run))
            ->filter(static fn (?string $id): bool => $id !== null && $id !== '')
            ->unique()
            ->values()
            ->all();

        /** @var array<string, string> */
        $starterNames = [];
        if ($starterIds !== []) {
            foreach (User::query()->whereIn('id', $starterIds)->get(['id', 'name']) as $u) {
                $starterNames[(string) $u->id] = (string) $u->name;
            }
        }

        $runs = $runsCollection
            ->map(fn (WorkflowRun $run): array => $this->runToListItem(
                $run,
                $currentUserId,
                $isAdmin,
                $starterNames,
            ));

        return Inertia::render('flows/Index', [
            'workflows' => $workflows->map(static fn (Workflow $w): array => [
                'id' => $w->id,
                'name' => $w->name,
                'description' => $w->description,
            ])->values()->all(),
            'runs' => $runs,
        ]);
    }

    public function store(Request $request, Workflow $workflow): RedirectResponse
    {
        Gate::authorize('start', $workflow);

        return $this->flowRunStarter->startOrRedirectToForm(
            $workflow,
            $request->user(),
            'flows.index',
        );
    }

    public function show(Request $request, WorkflowRun $run): Response|RedirectResponse
    {
        Gate::authorize('view', $run);

        if ($run->status !== RunStatus::Completed) {
            abort(404);
        }

        $workflow = $run->workflow;
        if ($workflow === null || ! $workflow->is_active) {
            abort(404);
        }

        $starterId = WorkflowStarterPayload::starterUserId($run);
        $iniciadaPor = '—';
        if ($starterId !== null) {
            $starter = User::query()->find($starterId);
            $iniciadaPor = $starter !== null ? (string) $starter->name : 'Conta removida ou desconhecida';
        }

        return Inertia::render('flows/Show', [
            'run_id' => $run->id,
            'workflow_name' => $workflow->name,
            'iniciada_por_label' => $iniciadaPor,
            'finished_at' => $run->finished_at?->toIso8601String(),
            'sections' => WorkflowFormProgress::completedRunReadOnlySections($run, $workflow),
        ]);
    }

    /**
     * @param  array<string, string>  $starterNames
     * @return array{id: int, workflow_id: int, workflow_name: string, status: string, status_label: string, created_at: string|null, iniciada_por_label: string, resume_url: string|null, view_url: string|null, error_message: string|null}
     */
    private function runToListItem(WorkflowRun $run, string $currentUserId, bool $isAdmin, array $starterNames): array
    {
        $workflow = $run->workflow;
        $workflowName = $workflow !== null ? (string) $workflow->name : '—';

        $starterId = WorkflowStarterPayload::starterUserId($run);
        $iniciadaPor = '—';
        if ($starterId !== null) {
            $iniciadaPor = $starterNames[$starterId] ?? 'Conta removida ou desconhecida';
        }

        $mayAccess = $isAdmin || ($starterId !== null && $starterId === $currentUserId);

        $resumeUrl = null;
        if ($run->status === RunStatus::Waiting && $mayAccess) {
            $token = FlowRunStarter::resumeTokenForWaitingRun($run);
            if (is_string($token) && $token !== '') {
                $resumeUrl = route('workflow-forms.show', ['token' => $token]);
            }
        }

        $viewUrl = null;
        if ($run->status === RunStatus::Completed && $mayAccess) {
            $viewUrl = route('flows.runs.show', ['run' => $run->id]);
        }

        return [
            'id' => $run->id,
            'workflow_id' => (int) $run->workflow_id,
            'workflow_name' => $workflowName,
            'status' => $run->status->value,
            'status_label' => $this->runStatusLabel($run->status),
            'created_at' => $run->created_at?->toIso8601String(),
            'iniciada_por_label' => $iniciadaPor,
            'resume_url' => $resumeUrl,
            'view_url' => $viewUrl,
            'error_message' => $run->status === RunStatus::Failed
                ? $this->truncateErrorMessage($run->error_message)
                : null,
        ];
    }

    private function runStatusLabel(RunStatus $status): string
    {
        return match ($status) {
            RunStatus::Waiting => 'Em curso',
            RunStatus::Running => 'A processar',
            RunStatus::Pending => 'Pendente',
            RunStatus::Completed => 'Concluída',
            RunStatus::Failed => 'Falhou',
            RunStatus::Cancelled => 'Cancelada',
        };
    }

    private function truncateErrorMessage(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return null;
        }

        if (mb_strlen($message) <= 200) {
            return $message;
        }

        return mb_substr($message, 0, 197).'…';
    }
}
