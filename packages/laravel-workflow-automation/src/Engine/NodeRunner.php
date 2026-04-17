<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Engine;

use Aftandilmmd\WorkflowAutomation\Contracts\NodeInterface;
use Aftandilmmd\WorkflowAutomation\Contracts\NodeMiddlewareInterface;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Closure;
use Throwable;

final class NodeRunner
{
    /** @var array<int, class-string<NodeMiddlewareInterface>|NodeMiddlewareInterface> */
    private array $middleware = [];

    /**
     * Add a middleware to the execution pipeline.
     *
     * @param  class-string<NodeMiddlewareInterface>|NodeMiddlewareInterface  $middleware
     */
    public function pushMiddleware(string|NodeMiddlewareInterface $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Execute a node with optional retry support.
     *
     * If the node has an 'error' output port and execution fails after all retries,
     * the error is routed to that port instead of being thrown.
     */
    public function run(
        NodeInterface $node,
        NodeInput $input,
        array $config,
        int $maxRetries = 0,
        int $retryDelayMs = 1000,
        string $backoffStrategy = 'exponential',
    ): NodeOutput {
        $attempt = 0;

        while (true) {
            try {
                return $this->executeWithMiddleware($node, $input, $config);
            } catch (Throwable $e) {
                $attempt++;

                if ($attempt > $maxRetries) {
                    // If the node declares an error port, route errors there
                    if (in_array('error', $node->outputPorts())) {
                        return NodeOutput::ports([
                            'main' => [],
                            'error' => [['error' => $e->getMessage(), 'input' => $input->items]],
                        ]);
                    }

                    throw $e;
                }

                $delay = $this->calculateDelay($attempt, $retryDelayMs, $backoffStrategy);
                usleep($delay * 1000);
            }
        }
    }

    private function executeWithMiddleware(
        NodeInterface $node,
        NodeInput $input,
        array $config,
    ): NodeOutput {
        if (empty($this->middleware)) {
            return $node->execute($input, $config);
        }

        $pipeline = array_reduce(
            array_reverse($this->middleware),
            function (Closure $next, string|NodeMiddlewareInterface $middleware): Closure {
                return function (NodeInterface $node, NodeInput $input, array $config) use ($middleware, $next): NodeOutput {
                    $instance = is_string($middleware) ? app($middleware) : $middleware;

                    return $instance->handle($node, $input, $config, $next);
                };
            },
            fn (NodeInterface $node, NodeInput $input, array $config): NodeOutput => $node->execute($input, $config),
        );

        return $pipeline($node, $input, $config);
    }

    /**
     * Calculate retry delay in milliseconds.
     */
    private function calculateDelay(int $attempt, int $baseDelayMs, string $strategy): int
    {
        $delay = match ($strategy) {
            'exponential' => $baseDelayMs * (2 ** ($attempt - 1)),
            default => $baseDelayMs * $attempt, // linear
        };

        // Add jitter (±25%)
        $jitter = (int) ($delay * 0.25);

        return $delay + random_int(-$jitter, $jitter);
    }
}
