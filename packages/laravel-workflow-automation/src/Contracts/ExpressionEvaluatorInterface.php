<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Contracts;

interface ExpressionEvaluatorInterface
{
    /**
     * Resolve a template string containing {{ expression }} blocks.
     */
    public function resolve(string $template, array $variables): mixed;

    /**
     * Recursively resolve all expression strings within a config array.
     */
    public function resolveConfig(array $config, array $variables): array;
}
