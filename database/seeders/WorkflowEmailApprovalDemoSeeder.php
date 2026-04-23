<?php

declare(strict_types=1);

namespace Database\Seeders;

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fluxo mínimo: disparo manual → e-mail de aprovação (AMP) → ramos approved / rejected (set_fields).
 * Use com {@see \App\Console\Commands\WorkflowEmailApprovalAmpExportCommand} ou testes Feature.
 */
final class WorkflowEmailApprovalDemoSeeder extends Seeder
{
    public const string WORKFLOW_NAME = 'PoC Aprovação por E-mail (AMP)';

    public function run(): void
    {
        Workflow::withTrashed()
            ->where('name', self::WORKFLOW_NAME)
            ->get()
            ->each(static function (Workflow $workflow): void {
                DB::transaction(static function () use ($workflow): void {
                    $workflow->edges()->delete();
                    $workflow->runs()->delete();
                    $workflow->nodes()->delete();
                    $workflow->tags()->detach();
                    $workflow->forceDelete();
                });
            });

        $to = (string) (env('WORKFLOW_EMAIL_APPROVAL_TO')
            ?: config('mail.from.address', 'test@example.com'));

        $workflow = Workflow::query()->create([
            'name' => self::WORKFLOW_NAME,
            'description' => 'Validação do nó email_approval com AMP (Gmail) e retoma por portas approved/rejected.',
            'is_active' => true,
            'run_async' => false,
        ]);

        $trigger = $workflow->addNode('Início', 'manual', []);

        $email = $workflow->addNode('Aprovação por e-mail', 'email_approval', [
            'to' => $to,
            'subject' => '[PoC] Aprovação necessária',
            'title' => 'Confirma esta operação?',
            'body' => "Este é um fluxo de demonstração.\n\nEscolha Aprovar ou Rejeitar.",
            'approve_label' => 'Aprovar',
            'reject_label' => 'Rejeitar',
            'ask_comment' => true,
            'link_expiration_hours' => 168,
        ]);

        $branchApproved = $workflow->addNode('Caminho aprovado', 'set_fields', [
            'fields' => ['branch' => 'approved'],
            'keep_existing' => true,
        ]);

        $branchRejected = $workflow->addNode('Caminho rejeitado', 'set_fields', [
            'fields' => ['branch' => 'rejected'],
            'keep_existing' => true,
        ]);

        $trigger->connect($email);
        $email->connect($branchApproved, 'approved', 'main');
        $email->connect($branchRejected, 'rejected', 'main');

        $workflow->activate();

        $this->command?->newLine();
        $this->command?->info(sprintf(
            'Fluxo "%s" criado (id %s). Execute: ./vendor/bin/sail artisan workflow:email-approval-amp-export',
            self::WORKFLOW_NAME,
            (string) $workflow->getKey(),
        ));
    }
}
