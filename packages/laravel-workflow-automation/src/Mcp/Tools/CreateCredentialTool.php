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

#[Name('create_credential')]
#[Title('Create Credential')]
#[Description('Create an encrypted credential. The data is encrypted at rest. Use list_credential_types to discover available types and their required fields.')]
final class CreateCredentialTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Display name for the credential')->required(),
            'type' => $schema->string()->description('Credential type key (e.g. bearer_token, basic_auth, header_auth, api_key)')->required(),
            'data' => $schema->object()->description('Secret data fields as key-value pairs (encrypted at rest)')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $credential = WorkflowCredential::create([
            'name' => $request->get('name'),
            'type' => $request->get('type'),
            'data' => $request->get('data'),
        ]);

        return Response::json([
            'id' => $credential->id,
            'name' => $credential->name,
            'type' => $credential->type,
        ]);
    }
}
