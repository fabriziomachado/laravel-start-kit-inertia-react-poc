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

#[Name('delete_credential')]
#[Title('Delete Credential')]
#[Description('Soft-delete a credential by ID.')]
final class DeleteCredentialTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'credential_id' => $schema->integer()->description('The credential ID to delete')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $credential = WorkflowCredential::findOrFail($request->get('credential_id'));
        $credential->delete();

        return Response::json([
            'deleted' => true,
            'id' => $credential->id,
        ]);
    }
}
