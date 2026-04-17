<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Credentials;

interface CredentialTypeInterface
{
    /**
     * Unique key for this credential type (e.g. 'bearer_token').
     */
    public static function getKey(): string;

    /**
     * Human-readable label.
     */
    public static function getLabel(): string;

    /**
     * Config schema fields for this credential type.
     * Same format as NodeInterface::configSchema().
     *
     * @return array<int, array<string, mixed>>
     */
    public static function schema(): array;
}
