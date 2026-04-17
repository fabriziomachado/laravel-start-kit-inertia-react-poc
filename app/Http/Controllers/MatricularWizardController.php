<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use Aftandilmmd\WorkflowAutomation\Services\WorkflowService;
use App\Models\User;
use App\Support\WorkflowFormProgress;
use Database\Seeders\WorkflowFormWizardExampleSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class MatricularWizardController
{
    public function index(Request $request): Response|RedirectResponse
    {
        $workflow = $this->resolveMatriculaWorkflow();

        if ($workflow === null) {
            return redirect()
                ->route('dashboard')
                ->with('matricula_error', 'O assistente de matrícula não está disponível. Contacte o administrador.');
        }

        $currentUserId = (string) $request->user()->id;
        $isAdmin = (bool) $request->user()->is_admin;

        $runsCollection = WorkflowRun::query()
            ->where('workflow_id', $workflow->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $starterIds = $runsCollection
            ->map(static fn (WorkflowRun $run): ?string => self::starterUserId($run))
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

        return Inertia::render('matricular/Index', [
            'workflow_name' => $workflow->name,
            'runs' => $runs,
        ]);
    }

    public function store(Request $request, WorkflowService $workflowService): RedirectResponse
    {
        $workflow = $this->resolveMatriculaWorkflow();

        if ($workflow === null || ! $workflow->is_active) {
            return redirect()
                ->route('matricular')
                ->with('matricula_error', 'O assistente de matrícula não está disponível. Contacte o administrador.');
        }

        $run = $workflowService->run($workflow, [['matricula_user_id' => $request->user()->id]]);

        if ($run->status !== RunStatus::Waiting) {
            return redirect()
                ->route('matricular')
                ->with('matricula_error', 'Não foi possível iniciar a matrícula. Tente novamente mais tarde.');
        }

        $token = $this->resumeTokenForWaitingRun($run);

        if (! is_string($token) || $token === '') {
            return redirect()
                ->route('matricular')
                ->with('matricula_error', 'Não foi possível iniciar a matrícula. Tente novamente mais tarde.');
        }

        return redirect()->route('workflow-forms.show', ['token' => $token]);
    }

    public function show(Request $request, WorkflowRun $run): Response|RedirectResponse
    {
        $workflow = $this->resolveMatriculaWorkflow();

        if ($workflow === null) {
            return redirect()
                ->route('dashboard')
                ->with('matricula_error', 'O assistente de matrícula não está disponível. Contacte o administrador.');
        }

        if ($run->workflow_id !== $workflow->id) {
            abort(404);
        }

        if ($run->status !== RunStatus::Completed) {
            abort(404);
        }

        $user = $request->user();
        $starterId = self::starterUserId($run);
        $isAdmin = (bool) $user->is_admin;
        $mayView = $isAdmin || ($starterId !== null && $starterId === (string) $user->id);

        if (! $mayView) {
            abort(403);
        }

        $iniciadaPor = '—';
        if ($starterId !== null) {
            $starter = User::query()->find($starterId);
            $iniciadaPor = $starter !== null ? (string) $starter->name : 'Conta removida ou desconhecida';
        }

        return Inertia::render('matricular/Show', [
            'run_id' => $run->id,
            'workflow_name' => $workflow->name,
            'iniciada_por_label' => $iniciadaPor,
            'finished_at' => $run->finished_at?->toIso8601String(),
            'sections' => WorkflowFormProgress::completedRunReadOnlySections($run, $workflow),
        ]);
    }

    private static function starterUserId(WorkflowRun $run): ?string
    {
        $raw = data_get($run->initial_payload, '0.matricula_user_id');
        if ($raw === null || $raw === '') {
            return null;
        }

        return (string) $raw;
    }

    private function resolveMatriculaWorkflow(): ?Workflow
    {
        $workflow = Workflow::query()
            ->where('name', WorkflowFormWizardExampleSeeder::WORKFLOW_NAME)
            ->first();

        if ($workflow === null || ! $workflow->is_active) {
            return null;
        }

        return $workflow;
    }

    private function resumeTokenForWaitingRun(WorkflowRun $run): ?string
    {
        $token = $run->nodeRuns()
            ->orderByDesc('id')
            ->first()
            ?->output['main'][0]['resume_token'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * @param  array<string, string>  $starterNames
     * @return array{id: int, status: string, status_label: string, created_at: string|null, iniciada_por_label: string, resume_url: string|null, view_url: string|null, error_message: string|null}
     */
    private function runToListItem(WorkflowRun $run, string $currentUserId, bool $isAdmin, array $starterNames): array
    {
        $starterId = self::starterUserId($run);
        $iniciadaPor = '—';
        if ($starterId !== null) {
            $iniciadaPor = $starterNames[$starterId] ?? 'Conta removida ou desconhecida';
        }

        $mayAccess = $isAdmin || ($starterId !== null && $starterId === $currentUserId);

        $resumeUrl = null;
        if ($run->status === RunStatus::Waiting && $mayAccess) {
            $token = $this->resumeTokenForWaitingRun($run);
            if (is_string($token) && $token !== '') {
                $resumeUrl = route('workflow-forms.show', ['token' => $token]);
            }
        }

        $viewUrl = null;
        if ($run->status === RunStatus::Completed && $mayAccess) {
            $viewUrl = route('matricular.runs.show', ['run' => $run->id]);
        }

        return [
            'id' => $run->id,
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
