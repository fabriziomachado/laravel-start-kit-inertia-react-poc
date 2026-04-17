<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Credentials;

use Aftandilmmd\WorkflowAutomation\Contracts\NodeInterface;
use Aftandilmmd\WorkflowAutomation\Contracts\NodeMiddlewareInterface;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowCredential;
use Closure;

final class CredentialResolutionMiddleware implements NodeMiddlewareInterface
{
    public function handle(
        NodeInterface $node,
        NodeInput $input,
        array $config,
        Closure $next,
    ): NodeOutput {
        if (isset($config['credential_id'])) {
            $credential = WorkflowCredential::findOrFail($config['credential_id']);
            $config['_credential'] = $credential->getDecryptedData();
        }

        return $next($node, $input, $config);
    }
}
