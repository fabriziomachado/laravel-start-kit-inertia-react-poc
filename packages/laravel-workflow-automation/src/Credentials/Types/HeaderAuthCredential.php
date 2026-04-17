<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Credentials\Types;

use Aftandilmmd\WorkflowAutomation\Credentials\CredentialTypeInterface;

final class HeaderAuthCredential implements CredentialTypeInterface
{
    public static function getKey(): string
    {
        return 'header_auth';
    }

    public static function getLabel(): string
    {
        return 'Header Auth';
    }

    public static function schema(): array
    {
        return [
            ['key' => 'header_name', 'type' => 'string', 'label' => 'Header Name', 'required' => true, 'placeholder' => 'X-API-Key'],
            ['key' => 'header_value', 'type' => 'password', 'label' => 'Header Value', 'required' => true],
        ];
    }
}
