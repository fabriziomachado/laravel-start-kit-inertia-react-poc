<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Exceptions;

final class PluginException extends WorkflowException
{
    public static function alreadyRegistered(string $id): self
    {
        return new self("Plugin '{$id}' is already registered.");
    }
}
