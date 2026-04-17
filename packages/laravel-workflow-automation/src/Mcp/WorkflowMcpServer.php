<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Mcp;

use Aftandilmmd\WorkflowAutomation\Mcp\Prompts\WorkflowBuilderPrompt;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\ActivateWorkflowTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\AddNodeTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\ConnectNodesTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\CreateCredentialTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\CreateFolderTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\CreateTagTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\CreateWorkflowTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\DeactivateWorkflowTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\DeleteCredentialTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\DeleteFolderTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\DeleteTagTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\DeleteWorkflowTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\DuplicateWorkflowTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\GetAvailableVariablesTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\GetRunTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\ListCredentialsTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\ListCredentialTypesTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\ListFoldersTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\ListNodeTypesTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\ListRunsTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\ListTagsTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\ListWorkflowsTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\PinNodeTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\RateLimitStatusTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\RemoveEdgeTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\RemoveNodeTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\RunWorkflowTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\ShowNodeTypeTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\ShowWorkflowTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\UnpinNodeTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\UpdateNodeTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\UpdateWorkflowTool;
use Aftandilmmd\WorkflowAutomation\Mcp\Tools\ValidateWorkflowTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Workflow Automation')]
#[Version('1.0.0')]
#[Instructions('This server manages workflow automation. Workflows are directed graphs of nodes (triggers, actions, conditions, controls) connected by edges. Use list_node_types to discover available node types before building workflows. Typical flow: create_workflow → add_node (repeat) → connect_nodes (repeat) → validate_workflow → activate_workflow.')]
final class WorkflowMcpServer extends Server
{
    protected array $tools = [
        // Workflow CRUD
        ListWorkflowsTool::class,
        ShowWorkflowTool::class,
        CreateWorkflowTool::class,
        UpdateWorkflowTool::class,
        DeleteWorkflowTool::class,
        ActivateWorkflowTool::class,
        DeactivateWorkflowTool::class,
        ValidateWorkflowTool::class,
        DuplicateWorkflowTool::class,

        // Node & Edge
        AddNodeTool::class,
        UpdateNodeTool::class,
        RemoveNodeTool::class,
        ConnectNodesTool::class,
        RemoveEdgeTool::class,
        PinNodeTool::class,
        UnpinNodeTool::class,

        // Execution
        RunWorkflowTool::class,
        GetRunTool::class,
        ListRunsTool::class,
        RateLimitStatusTool::class,

        // Registry & Discovery
        ListNodeTypesTool::class,
        ShowNodeTypeTool::class,
        GetAvailableVariablesTool::class,

        // Tags & Folders
        ListTagsTool::class,
        CreateTagTool::class,
        DeleteTagTool::class,
        ListFoldersTool::class,
        CreateFolderTool::class,
        DeleteFolderTool::class,

        // Credentials
        ListCredentialsTool::class,
        CreateCredentialTool::class,
        DeleteCredentialTool::class,
        ListCredentialTypesTool::class,
    ];

    protected array $prompts = [
        WorkflowBuilderPrompt::class,
    ];
}
