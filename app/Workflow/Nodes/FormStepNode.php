<?php

declare(strict_types=1);

namespace App\Workflow\Nodes;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\Contracts\NodeInterface;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use Aftandilmmd\WorkflowAutomation\Nodes\HasDocumentation;
use Illuminate\Support\Str;

#[AsWorkflowNode(key: 'form_step', type: NodeType::Action, label: 'Form Step')]
final class FormStepNode implements NodeInterface
{
    use HasDocumentation;

    public static function configSchema(): array
    {
        return [
            ['key' => 'title', 'type' => 'string', 'label' => 'Título', 'required' => true, 'supports_expression' => true],
            ['key' => 'description', 'type' => 'textarea', 'label' => 'Descrição', 'required' => false, 'supports_expression' => true],
            ['key' => 'submit_label', 'type' => 'string', 'label' => 'Texto do botão', 'required' => false],
            ['key' => 'fields', 'type' => 'array_of_objects', 'label' => 'Campos', 'required' => true, 'schema' => [
                ['key' => 'key', 'type' => 'string', 'label' => 'Chave', 'required' => true],
                ['key' => 'label', 'type' => 'string', 'label' => 'Rótulo', 'required' => true],
                ['key' => 'type', 'type' => 'select', 'label' => 'Tipo', 'required' => true, 'options' => ['string', 'textarea', 'email', 'boolean', 'number', 'select', 'choice_cards']],
                ['key' => 'required', 'type' => 'boolean', 'label' => 'Obrigatório'],
                ['key' => 'placeholder', 'type' => 'string', 'label' => 'Placeholder'],
                ['key' => 'options', 'type' => 'string', 'label' => 'Opções (CSV, apenas select)', 'required' => false],
                ['key' => 'choices', 'type' => 'array_of_objects', 'label' => 'Cartões (apenas choice_cards)', 'required' => false, 'schema' => [
                    ['key' => 'value', 'type' => 'string', 'label' => 'Valor', 'required' => true],
                    ['key' => 'label', 'type' => 'string', 'label' => 'Título', 'required' => true],
                    ['key' => 'description', 'type' => 'string', 'label' => 'Descrição', 'required' => false],
                    ['key' => 'icon', 'type' => 'string', 'label' => 'Ícone Lucide', 'required' => false],
                ]],
            ]],
        ];
    }

    public static function outputSchema(): array
    {
        return [
            'main' => [['key' => '*', 'type' => 'mixed', 'label' => 'Campos preenchidos no formulário']],
        ];
    }

    public function inputPorts(): array
    {
        return ['main'];
    }

    public function outputPorts(): array
    {
        return ['main'];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $token = Str::uuid()->toString();

        $run = WorkflowRun::find($input->context->workflowRunId);

        if ($run) {
            $run->update([
                'status' => RunStatus::Waiting,
                'context' => $input->context->getAllOutputs(),
            ]);
        }

        $title = (string) ($config['title'] ?? '');
        $description = $config['description'] ?? '';
        $submitLabel = (string) ($config['submit_label'] ?? 'Continuar');
        $fields = $config['fields'] ?? [];

        return NodeOutput::ports([
            'main' => [[
                'resume_token' => $token,
                'title' => $title,
                'description' => $description,
                'submit_label' => $submitLabel,
                'fields' => $fields,
            ]],
        ]);
    }
}
