<?php

declare(strict_types=1);

namespace App\Flows;

use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use Aftandilmmd\WorkflowAutomation\Services\WorkflowService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class FlowRunStarter
{
    public function __construct(
        private WorkflowService $workflowService,
    ) {}

    public static function resumeTokenForWaitingRun(WorkflowRun $run): ?string
    {
        $token = $run->nodeRuns()
            ->orderByDesc('id')
            ->first()
            ?->output['main'][0]['resume_token'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * Inicia o workflow e redirecciona para o primeiro passo (form) ou devolve erro à rota de listagem.
     *
     * @param  string  $flowsIndexRoute  Nome da rota para redirect em erro (ex.: flows.index)
     */
    public function startOrRedirectToForm(
        Workflow $workflow,
        User $user,
        string $flowsIndexRoute,
        string $sessionErrorKey = 'flows_error',
    ): RedirectResponse {
        if (! $workflow->is_active) {
            return redirect()
                ->route($flowsIndexRoute)
                ->with($sessionErrorKey, 'Este fluxo não está disponível.');
        }

        $run = $this->workflowService->run($workflow, WorkflowStarterPayload::forUser($user));

        if ($run->status !== RunStatus::Waiting) {
            return redirect()
                ->route($flowsIndexRoute)
                ->with($sessionErrorKey, 'Não foi possível iniciar o fluxo. Tente novamente mais tarde.');
        }

        $token = self::resumeTokenForWaitingRun($run);

        if (! is_string($token) || $token === '') {
            return redirect()
                ->route($flowsIndexRoute)
                ->with($sessionErrorKey, 'Não foi possível iniciar o fluxo. Tente novamente mais tarde.');
        }

        return redirect()->route('workflow-forms.show', ['token' => $token]);
    }
}
