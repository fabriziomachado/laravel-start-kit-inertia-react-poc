<?php

declare(strict_types=1);

namespace App\Workflow\Nodes;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\Contracts\NodeInterface;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeRunStatus;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use Aftandilmmd\WorkflowAutomation\Nodes\HasDocumentation;
use App\Mail\WorkflowApprovalAmpMail;
use App\Support\WorkflowApprovalToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

#[AsWorkflowNode(key: 'email_approval', type: NodeType::Action, label: 'E-mail de Aprovação')]
final class EmailApprovalNode implements NodeInterface
{
    use HasDocumentation;

    public static function configSchema(): array
    {
        return [
            ['key' => 'to', 'type' => 'string', 'label' => 'Destinatário', 'required' => true, 'supports_expression' => true],
            ['key' => 'subject', 'type' => 'string', 'label' => 'Assunto', 'required' => true, 'supports_expression' => true],
            ['key' => 'title', 'type' => 'string', 'label' => 'Título (corpo do e-mail)', 'required' => false, 'supports_expression' => true],
            ['key' => 'body', 'type' => 'textarea', 'label' => 'Mensagem', 'required' => false, 'supports_expression' => true],
            ['key' => 'approve_label', 'type' => 'string', 'label' => 'Texto do botão aprovar', 'required' => false],
            ['key' => 'reject_label', 'type' => 'string', 'label' => 'Texto do botão rejeitar', 'required' => false],
            ['key' => 'ask_comment', 'type' => 'boolean', 'label' => 'Pedir comentário', 'required' => false],
            ['key' => 'link_expiration_hours', 'type' => 'integer', 'label' => 'Validade do link (horas)', 'required' => false],
            ['key' => 'from_address', 'type' => 'string', 'label' => 'From (e-mail)', 'required' => false],
            ['key' => 'from_name', 'type' => 'string', 'label' => 'From (nome)', 'required' => false],
        ];
    }

    public static function outputSchema(): array
    {
        $row = [
            ['key' => 'decision', 'type' => 'string', 'label' => 'approve ou reject'],
            ['key' => 'comment', 'type' => 'string', 'label' => 'Comentário'],
            ['key' => 'decided_at', 'type' => 'string', 'label' => 'ISO8601'],
            ['key' => 'decided_by_email', 'type' => 'string', 'label' => 'E-mail do decisor (se conhecido)'],
        ];

        return [
            'approved' => $row,
            'rejected' => $row,
        ];
    }

    public function inputPorts(): array
    {
        return ['main'];
    }

    public function outputPorts(): array
    {
        return ['approved', 'rejected'];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $token = Str::uuid()->toString();

        $run = WorkflowRun::query()->find($input->context->workflowRunId);

        if ($run === null) {
            return NodeOutput::ports(['approved' => [], 'rejected' => []]);
        }

        $nodeRun = $run->nodeRuns()
            ->where('status', NodeRunStatus::Running)
            ->latest('id')
            ->first();

        $nodeId = $nodeRun?->node_id ?? 0;

        if ($nodeId === 0) {
            return NodeOutput::ports(['approved' => [], 'rejected' => []]);
        }

        $ttlHours = max(1, (int) ($config['link_expiration_hours'] ?? 168));
        $expiresAt = now()->addHours($ttlHours);
        $ttlSeconds = $ttlHours * 3600;

        Cache::put(WorkflowApprovalToken::cacheKey($run->id, $nodeId), $token, $expiresAt);

        $signedParams = [
            'run' => $run->id,
            'node' => $nodeId,
            'token' => $token,
        ];

        $actionUrl = URL::temporarySignedRoute('workflow-approvals.submit', $expiresAt, $signedParams);
        $approveGetUrl = URL::temporarySignedRoute('workflow-approvals.fallback', $expiresAt, [
            ...$signedParams,
            'decision' => 'approve',
        ]);
        $rejectGetUrl = URL::temporarySignedRoute('workflow-approvals.fallback', $expiresAt, [
            ...$signedParams,
            'decision' => 'reject',
        ]);

        $run->update([
            'status' => RunStatus::Waiting,
            'context' => $input->context->getAllOutputs(),
        ]);

        $to = mb_trim((string) ($config['to'] ?? ''));
        $subject = (string) ($config['subject'] ?? 'Aprovação');
        $title = (string) ($config['title'] ?? $subject);
        $body = (string) ($config['body'] ?? '');
        $approveLabel = (string) ($config['approve_label'] ?? 'Aprovar');
        $rejectLabel = (string) ($config['reject_label'] ?? 'Rejeitar');
        $askComment = (bool) ($config['ask_comment'] ?? true);
        $fromAddress = isset($config['from_address']) ? (string) $config['from_address'] : null;
        $fromName = isset($config['from_name']) ? (string) $config['from_name'] : null;
        $fromAddress = filled(mb_trim((string) $fromAddress)) ? mb_trim((string) $fromAddress) : null;
        $fromName = filled(mb_trim((string) $fromName)) ? mb_trim((string) $fromName) : null;

        $ampSourceOrigin = mb_rtrim((string) config('app.url'), '/');

        $viewData = [
            'title' => $title,
            'body' => $body,
            'approveLabel' => $approveLabel,
            'rejectLabel' => $rejectLabel,
            'askComment' => $askComment,
            'actionUrl' => $actionUrl,
            'ampSourceOrigin' => $ampSourceOrigin,
            'approveGetUrl' => $approveGetUrl,
            'rejectGetUrl' => $rejectGetUrl,
        ];

        if ($to !== '') {
            Mail::to($to)->send(new WorkflowApprovalAmpMail(
                subjectLine: $subject,
                emailApprovalPayload: $viewData,
                fromAddress: $fromAddress,
                fromName: $fromName,
            ));
        }

        return NodeOutput::ports([
            'approved' => [],
            'rejected' => [],
        ]);
    }
}
