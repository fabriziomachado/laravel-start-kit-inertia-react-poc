<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Jobs\ResumeWorkflowJob;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use App\Support\WorkflowApprovalToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class WorkflowApprovalController
{
    public function fallback(Request $request, WorkflowRun $run, int $node, string $token, string $decision): Response
    {
        $cacheKey = WorkflowApprovalToken::cacheKey($run->id, $node);
        $cached = Cache::get($cacheKey);

        if (! is_string($cached) || ! hash_equals($cached, $token)) {
            return response('Este link já foi utilizado ou não é válido.', 409, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        if (! $run->isWaiting()) {
            return response('Esta execução já não está à espera de aprovação.', 409, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        $comment = $request->query('comment');
        $comment = is_string($comment) ? mb_substr($comment, 0, 2000) : null;

        $resume = $this->resumeIfValidDecision($run, $node, $decision, $comment, $request);

        if ($resume['error'] !== null) {
            return response($resume['error'], $resume['status'], [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        Cache::forget($cacheKey);

        return response(
            view('emails.workflow.approval-fallback-thanks', [
                'decision' => $decision,
            ]),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    public function submit(Request $request, WorkflowRun $run, int $node, string $token): JsonResponse
    {
        $cacheKey = WorkflowApprovalToken::cacheKey($run->id, $node);
        $cached = Cache::get($cacheKey);

        if (! is_string($cached) || ! hash_equals($cached, $token)) {
            return $this->ampJsonResponse($request, ['error' => 'Este link já foi utilizado ou não é válido.'], 409);
        }

        if (! $run->isWaiting()) {
            return $this->ampJsonResponse($request, ['error' => 'Esta execução já não está à espera de aprovação.'], 409);
        }

        try {
            $validated = $request->validate([
                'decision' => ['required', 'in:approve,reject'],
                'comment' => ['nullable', 'string', 'max:2000'],
            ]);
        } catch (ValidationException $e) {
            $first = collect($e->errors())->flatten()->first();

            return $this->ampJsonResponse($request, [
                'error' => is_string($first) ? $first : 'Pedido inválido.',
            ], 422);
        }

        // Em AMP real o runtime acrescenta __amp_source_origin ao query string do
        // action-xhr. Em requisições directas (tests, navegação manual) ainda aceitamos
        // pelo corpo para manter a compatibilidade.
        $rawOrigin = $request->query('__amp_source_origin')
            ?? $request->input('__amp_source_origin');

        $origin = mb_rtrim((string) $rawOrigin, '/');
        $expected = mb_rtrim((string) config('app.url'), '/');

        if ($origin === '' || $origin !== $expected) {
            return $this->ampJsonResponse($request, ['error' => 'Origem inválida.'], 403);
        }

        $resume = $this->resumeIfValidDecision(
            $run,
            $node,
            (string) $validated['decision'],
            $validated['comment'] ?? null,
            $request,
        );

        if ($resume['error'] !== null) {
            return $this->ampJsonResponse($request, ['error' => $resume['error']], $resume['status']);
        }

        Cache::forget($cacheKey);

        return $this->ampJsonResponse($request, [
            'ok' => true,
            'message' => 'Resposta registrada',
        ]);
    }

    /**
     * @return array{error: ?string, status: int}
     */
    private function resumeIfValidDecision(
        WorkflowRun $run,
        int $node,
        string $decision,
        ?string $comment,
        Request $request,
    ): array {
        $resumePort = $decision === 'approve' ? 'approved' : 'rejected';

        $payload = [
            'decision' => $decision,
            'comment' => $comment,
            'decided_at' => now()->toIso8601String(),
            'decided_by_email' => $request->input('__amp_source_origin_hint') ?? $request->query('decided_by_email'),
        ];

        ResumeWorkflowJob::dispatchSync(
            workflowRunId: $run->id,
            resumeFromNodeId: $node,
            payload: $payload,
            resumePort: $resumePort,
        );

        $fresh = $run->fresh();

        if ($fresh->status === RunStatus::Failed) {
            return ['error' => 'Não foi possível concluir o fluxo.', 'status' => 500];
        }

        return ['error' => null, 'status' => 200];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ampJsonResponse(Request $request, array $payload, int $status = 200): JsonResponse
    {
        $allowOrigin = $this->resolveAmpAccessControlAllowOrigin($request);

        return response()->json($payload, $status, [
            'Access-Control-Allow-Origin' => $allowOrigin,
            'Access-Control-Allow-Credentials' => 'true',
            'AMP-Access-Control-Allow-Source-Origin' => mb_rtrim((string) config('app.url'), '/'),
            'Access-Control-Expose-Headers' => 'AMP-Access-Control-Allow-Source-Origin',
        ]);
    }

    private function resolveAmpAccessControlAllowOrigin(Request $request): string
    {
        $origin = $request->headers->get('Origin');

        if ($origin !== null && preg_match('#^https://([a-z0-9-]+\.)*google\.com$#i', $origin) === 1) {
            return $origin;
        }

        return mb_rtrim((string) config('app.url'), '/');
    }
}
