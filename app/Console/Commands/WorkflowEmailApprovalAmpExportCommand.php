<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Aftandilmmd\WorkflowAutomation\Engine\GraphExecutor;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use App\Support\WorkflowApprovalToken;
use Database\Seeders\WorkflowEmailApprovalDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

/**
 * Emite o HTML AMP4EMAIL para testar no playground oficial do Gmail AMP.
 *
 * @see https://amp.gmail.dev/playground/
 */
final class WorkflowEmailApprovalAmpExportCommand extends Command
{
    protected $signature = 'workflow:email-approval-amp-export';

    protected $description = 'Gera HTML AMP (copiar/colar) para o playground amp.gmail.dev, usando o fluxo demo em execução Waiting.';

    public function handle(GraphExecutor $executor): int
    {
        $this->call(WorkflowEmailApprovalDemoSeeder::class);

        $workflow = Workflow::query()
            ->where('name', WorkflowEmailApprovalDemoSeeder::WORKFLOW_NAME)
            ->firstOrFail();

        $run = $executor->execute($workflow, [[]]);

        $emailNode = $workflow->nodes()->where('node_key', 'email_approval')->firstOrFail();

        $token = Cache::get(WorkflowApprovalToken::cacheKey($run->id, $emailNode->id));

        if (! is_string($token)) {
            $this->error('Não foi possível obter o token de aprovação (cache). O run ficou em Waiting?');

            return self::FAILURE;
        }

        $ttlHours = max(1, (int) ($emailNode->config['link_expiration_hours'] ?? 168));
        $expiresAt = now()->addHours($ttlHours);

        $signedParams = [
            'run' => $run->id,
            'node' => $emailNode->id,
            'token' => $token,
        ];

        $actionUrl = URL::temporarySignedRoute('workflow-approvals.submit', $expiresAt, $signedParams);
        $ampSourceOrigin = mb_rtrim((string) config('app.url'), '/');

        $cfg = $emailNode->config ?? [];

        $this->newLine();
        $this->info('1) Copie TODO o HTML emitido abaixo (começa em <!doctype html>).');
        $this->info('2) Abra o playground: https://amp.gmail.dev/playground/');
        $this->info('3) Cole no editor e use "Validate" / pré-visualização.');
        $this->warn('O action-xhr só funciona se o APP_URL for HTTPS público (ex.: túnel ngrok) alinhado à app que recebe o POST.');
        $this->newLine();

        $this->line(view('emails.workflow.approval-amp', [
            'title' => (string) ($cfg['title'] ?? 'Aprovação'),
            'body' => (string) ($cfg['body'] ?? ''),
            'approveLabel' => (string) ($cfg['approve_label'] ?? 'Aprovar'),
            'rejectLabel' => (string) ($cfg['reject_label'] ?? 'Rejeitar'),
            'askComment' => (bool) ($cfg['ask_comment'] ?? true),
            'actionUrl' => $actionUrl,
            'ampSourceOrigin' => $ampSourceOrigin,
        ])->render());

        $this->newLine();

        return self::SUCCESS;
    }
}
