<?php

declare(strict_types=1);

namespace Database\Seeders;

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class WorkflowFormWizardExampleSeeder extends Seeder
{
    public const string WORKFLOW_NAME = 'Matrícula de Calouros - POC';

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

        $workflow = Workflow::query()->create([
            'name' => self::WORKFLOW_NAME,
            'description' => 'Matricula inicial para alunos calouros da unesc',
            'is_active' => false,
        ]);

        $trigger = $workflow->addNode('Início', 'manual', []);

        $stepPersonal = $workflow->addNode('Passo1', 'form_step', [
            'title' => 'Dados pessoais',
            'description' => 'Indique o nome e o e-mail.',
            'submit_label' => 'Seguinte',
            'fields' => [
                ['key' => 'name', 'label' => 'Nome', 'type' => 'string', 'required' => true],
                ['key' => 'email', 'label' => 'E-mail', 'type' => 'email', 'required' => true],
            ],
        ]);

        $stepIngresso = $workflow->addNode('Ingresso', 'form_step', [
            'title' => 'Forma de ingresso.',
            'description' => 'Como deseja entrar na nossa instituição? Escolha a melhor opção para si.',
            'submit_label' => 'Próximo passo',
            'fields' => [
                [
                    'key' => 'forma_ingresso',
                    'label' => 'Forma de ingresso',
                    'type' => 'choice_cards',
                    'required' => true,
                    'choices' => [
                        [
                            'value' => 'historico_escolar',
                            'label' => 'Histórico Escolar',
                            'description' => 'Use as suas notas do ensino médio para ingressar sem vestibular.',
                            'icon' => 'ScrollText',
                        ],
                        [
                            'value' => 'enem',
                            'label' => 'Nota do ENEM',
                            'description' => 'Ingresso com a sua pontuação do ENEM (anos 2010 a 2023).',
                            'icon' => 'GraduationCap',
                        ],
                        [
                            'value' => 'vestibular_online',
                            'label' => 'Vestibular Online',
                            'description' => 'Realize uma redação online agora mesmo, de forma rápida.',
                            'icon' => 'FilePenLine',
                        ],
                        [
                            'value' => 'transferencia',
                            'label' => 'Transferência',
                            'description' => 'Venha de outra instituição com condições especiais.',
                            'icon' => 'ArrowRightLeft',
                        ],
                    ],
                ],
            ],
        ]);

        $stepDetails = $workflow->addNode('Passo2', 'form_step', [
            'title' => 'Detalhes',
            'description' => 'Motivo e aceitação dos termos.',
            'submit_label' => 'Concluir',
            'fields' => [
                ['key' => 'reason', 'label' => 'Motivo', 'type' => 'textarea', 'required' => true],
                ['key' => 'accept_terms', 'label' => 'Aceito os termos', 'type' => 'boolean', 'required' => true],
            ],
        ]);

        $merge = $workflow->addNode('Consolidar', 'set_fields', [
            'fields' => [
                'full_name' => '{{ nodes.Passo1.main.0.name }}',
                'email' => '{{ nodes.Passo1.main.0.email }}',
                'forma_ingresso' => '{{ nodes.Ingresso.main.0.forma_ingresso }}',
                'reason' => '{{ nodes.Passo2.main.0.reason }}',
                'accept_terms' => '{{ nodes.Passo2.main.0.accept_terms }}',
            ],
        ]);

        $trigger->connect($stepPersonal);
        $stepPersonal->connect($stepIngresso);
        $stepIngresso->connect($stepDetails);
        $stepDetails->connect($merge);

        $workflow->activate();

        $this->command?->newLine();
        $this->command?->info(sprintf(
            'Fluxo de exemplo disponível em /flows (workflow id %s).',
            (string) $workflow->getKey(),
        ));

        $trigger->update(['position_x' => 80, 'position_y' => 160]);
        $stepPersonal->update(['position_x' => 280, 'position_y' => 160]);
        $stepIngresso->update(['position_x' => 480, 'position_y' => 160]);
        $stepDetails->update(['position_x' => 680, 'position_y' => 160]);
        $merge->update(['position_x' => 880, 'position_y' => 160]);
    }
}
