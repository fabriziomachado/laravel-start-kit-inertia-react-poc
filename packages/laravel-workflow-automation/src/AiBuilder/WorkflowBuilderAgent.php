<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\AiBuilder;

use Aftandilmmd\WorkflowAutomation\Mcp\Prompts\WorkflowBuilderPrompt;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Registry\NodeRegistry;
use Aftandilmmd\WorkflowAutomation\Services\WorkflowService;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

#[MaxSteps(25)]
final class WorkflowBuilderAgent implements Agent, HasTools
{
    use Promptable;

    public function __construct(
        protected Workflow $workflow,
        protected NodeRegistry $registry,
    ) {}

    public function instructions(): string
    {
        $base = WorkflowBuilderPrompt::buildSystemPromptText($this->registry->all());
        $workflowId = $this->workflow->id;
        $workflowName = $this->workflow->name;

        return $base.<<<EXTRA

        ## Your Role

        You are modifying workflow **"{$workflowName}"** (ID: {$workflowId}).
        ALWAYS use workflow_id={$workflowId} when calling tools that require it.

        ## Step-by-Step Process

        1. Call show_workflow(workflow_id={$workflowId}) to see existing nodes and edges.
        2. For EACH node type you plan to add, call show_node_type(node_key="...") to get the exact config schema with all fields, types, and options.
        3. Add nodes with add_node, providing config with ALL required fields populated based on the schema from step 2.
        4. Connect nodes with connect_nodes using the correct ports.
        5. Respond with a brief explanation of what you did.

        ## Tool parameters: config

        For **add_node** and **update_node**, pass **config** as a JSON **string** (for example `"{}"` or `"{\"url\":\"https://example.com\"}"`), because the API validates tool parameters in strict mode.

        ## CRITICAL: Config Must Be Filled

        When adding a node, the config object MUST contain all required fields. NEVER pass an empty config `{}` — the node will be broken.

        Before calling add_node, ALWAYS call show_node_type first to learn the exact config fields. Then fill them in.

        Examples of CORRECT add_node calls (config is always a JSON string):
        - add_node(workflow_id={$workflowId}, node_key="model_event", name="User Created", config="{\"model\":\"App\\\\Models\\\\User\",\"events\":[\"created\"]}")
        - add_node(workflow_id={$workflowId}, node_key="send_mail", name="Welcome Email", config="{\"send_mode\":\"inline\",\"to\":\"{{ item.email }}\",\"subject\":\"Welcome!\",\"body\":\"Hello {{ item.name }}\"}")
        - add_node(workflow_id={$workflowId}, node_key="http_request", name="Call API", config="{\"url\":\"https://api.example.com\",\"method\":\"POST\",\"body\":{\"key\":\"{{ item.id }}\"}}")
        - add_node(workflow_id={$workflowId}, node_key="if_condition", name="Check Status", config="{\"field\":\"{{ item.status }}\",\"operator\":\"==\",\"value\":\"active\"}")
        - add_node(workflow_id={$workflowId}, node_key="manual", name="Manual Trigger", config='{}')

        Examples of WRONG (broken) calls:
        - add_node(workflow_id={$workflowId}, node_key="send_mail", name="Email", config='{}')  ← WRONG: JSON must include to, subject, body
        - add_node(workflow_id={$workflowId}, node_key="http_request", name="API", config='{}')  ← WRONG: JSON must include url, method

        ## Important Rules

        - For model_select fields, use fully-qualified class names like "App\\\\Models\\\\User"
        - For expression fields (supports_expression), use {{ }} syntax like "{{ item.email }}"
        - When building from scratch, start with a trigger node, then actions/conditions, then connect them all
        - If the user mentions a model name like "User", infer it as "App\\\\Models\\\\User"

        ## Response Format

        Keep your text responses VERY short. This is a workflow builder tool, NOT a chatbot.
        After making changes, respond with ONLY:
        - One short sentence summarizing what you did
        - A bullet list of the nodes added/modified (name and type only)

        Example response:
        "Stok takip akışı oluşturuldu.
        - Model Event: Ürün stok güncelleme
        - If Condition: Stok < 1000 kontrolü
        - Send Mail: mmd@mmd.com bildirim"

        Do NOT write long explanations, step-by-step descriptions, or repeat the user's request back to them. Be concise.
        EXTRA;
    }

    public function tools(): iterable
    {
        $service = app(WorkflowService::class);

        return [
            new ShowWorkflowAiBuilderTool,
            new ShowNodeTypeAiBuilderTool($this->registry),
            new ListNodeTypesAiBuilderTool($this->registry),
            new AddNodeAiBuilderTool($service),
            new UpdateNodeAiBuilderTool,
            new RemoveNodeAiBuilderTool($service),
            new ConnectNodesAiBuilderTool($service),
            new RemoveEdgeAiBuilderTool($service),
        ];
    }
}
