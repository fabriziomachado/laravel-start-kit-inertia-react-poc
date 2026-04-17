<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Credentials\Types;

use Aftandilmmd\WorkflowAutomation\Credentials\CredentialTypeInterface;

final class ApiKeyCredential implements CredentialTypeInterface
{
    public static function getKey(): string
    {
        return 'api_key';
    }

    public static function getLabel(): string
    {
        return 'API Key';
    }

    public static function schema(): array
    {
        return [
            ['key' => 'api_key', 'type' => 'password', 'label' => 'API Key', 'required' => true],
        ];
    }
}
