<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Mcp\Tools;

use Aftandilmmd\WorkflowAutomation\Credentials\CredentialTypeRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_credential_types')]
#[Title('List Credential Types')]
#[Description('List all available credential types with their schemas. Use this to know which fields are required when creating a credential.')]
#[IsReadOnly]
final class ListCredentialTypesTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response
    {
        $registry = app(CredentialTypeRegistry::class);

        return Response::json(['types' => $registry->all()]);
    }
}
