<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Mcp\Tools;

use Aftandilmmd\WorkflowAutomation\Models\WorkflowCredential;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_credentials')]
#[Title('List Credentials')]
#[Description('List all stored credentials. Returns id, name, and type — never returns decrypted secret data.')]
#[IsReadOnly]
final class ListCredentialsTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response
    {
        $credentials = WorkflowCredential::latest()
            ->get(['id', 'name', 'type', 'meta', 'created_at'])
            ->map(fn (WorkflowCredential $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'type' => $c->type,
                'meta' => $c->meta,
            ])
            ->all();

        return Response::json(['items' => $credentials]);
    }
}
