<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Credentials\Types;

use Aftandilmmd\WorkflowAutomation\Credentials\CredentialTypeInterface;

final class BasicAuthCredential implements CredentialTypeInterface
{
    public static function getKey(): string
    {
        return 'basic_auth';
    }

    public static function getLabel(): string
    {
        return 'Basic Auth';
    }

    public static function schema(): array
    {
        return [
            ['key' => 'username', 'type' => 'string', 'label' => 'Username', 'required' => true],
            ['key' => 'password', 'type' => 'password', 'label' => 'Password', 'required' => true],
        ];
    }
}
