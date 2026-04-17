<div v-pre>

# AI Builder

The AI Builder is an in-app chat interface inside the [Visual Editor](/ui-editor) that lets you create and modify workflows using natural language. It's powered by `laravel/ai` and reuses the package's [MCP tools](/mcp) server-side — describe what you want, and the AI agent builds the nodes and edges for you in real-time.

## Requirements

```bash
composer require laravel/ai laravel/mcp
```

You also need an API key for your chosen AI provider. For example, with OpenAI:

```ini
OPENAI_API_KEY=sk-...
```

## Configuration

The AI Builder is enabled by default. Customize it in `config/workflow-automation.php`:

```php
'ai_builder' => [
    'enabled'          => env('WORKFLOW_AI_BUILDER_ENABLED', true),
    'default_provider' => env('WORKFLOW_AI_BUILDER_PROVIDER', 'openai'),
    'default_model'    => env('WORKFLOW_AI_BUILDER_MODEL', 'gpt-4o'),
    'max_steps'        => 25,
],
```

| Environment Variable | Default | Description |
|----------------------|---------|-------------|
| `WORKFLOW_AI_BUILDER_ENABLED` | `true` | Enable or disable the AI Builder |
| `WORKFLOW_AI_BUILDER_PROVIDER` | `openai` | Default AI provider |
| `WORKFLOW_AI_BUILDER_MODEL` | `gpt-4o` | Default model |

## Supported Providers

| Provider | Key | Example Models |
|----------|-----|----------------|
| OpenAI | `openai` | gpt-4o, gpt-4.1 |
| Anthropic | `anthropic` | claude-sonnet-4-20250514 |
| Google Gemini | `gemini` | gemini-2.0-flash |
| Groq | `groq` | llama-3.3-70b |
| Mistral | `mistral` | mistral-large-latest |
| DeepSeek | `deepseek` | deepseek-chat |
| Ollama | `ollama` | llama3 |
| xAI | `xai` | grok-2 |
| Cohere | `cohere` | command-r-plus |

## Usage

1. Open a workflow in the Visual Editor
2. Click the **AI** button in the header toolbar
3. Select a provider and model from the dropdowns (or leave as default)
4. Type a natural language description of what you want
5. The AI streams its response and builds nodes/edges in real-time
6. When complete, the canvas refreshes automatically with the new workflow

::: tip
You can use the AI Builder on an empty workflow to build from scratch, or on an existing workflow to add more nodes.
:::

## Example Prompts

```
When a new user registers, send them a welcome email.
```

```
Create a workflow that checks order total — if over $100, email the admin;
otherwise, send a POST request to the inventory API.
```

```
Add error handling to the existing nodes. Connect all error ports to
an error handler node.
```

```
Loop over the order items, run AI analysis on each one, then wait 5 minutes
before proceeding.
```

## How It Works

The AI Builder runs a `laravel/ai` agent with access to 8 MCP tools:

```
1. show_workflow        → Read current state
2. show_node_type       → Get config schema for a node type
3. list_node_types      → Discover available nodes
4. add_node             → Create a node with full config
5. update_node          → Modify a node's name or config
6. remove_node          → Delete a node and its edges
7. connect_nodes        → Create an edge between nodes
8. remove_edge          → Remove an edge
```

The agent follows a loop: inspect the workflow → look up node schemas → add nodes with complete configuration → connect them. It runs up to 25 steps per request.

## REST API

The AI Builder exposes an SSE streaming endpoint:

```
POST /{prefix}/workflows/{workflow}/ai-build
Content-Type: application/json
```

**Request body:**

```json
{
  "prompt": "Send a welcome email when a user registers",
  "provider": "openai",
  "model": "gpt-4o"
}
```

| Field | Required | Description |
|-------|----------|-------------|
| `prompt` | Yes | Natural language instruction (max 2000 chars) |
| `provider` | No | Override default provider |
| `model` | No | Override default model |

**Response:** Server-Sent Events stream

```
data: {"type":"text_delta","delta":"I'll create..."}
data: {"type":"tool_call","tool_name":"add_node","arguments":{...}}
data: {"type":"tool_result","tool_name":"add_node","result":"{...}"}
data: {"type":"text_delta","delta":"Done! I added..."}
data: [DONE]
```

## AI Builder vs MCP Server

Both features use the same underlying tools, but serve different use cases:

| | AI Builder | MCP Server |
|---|-----------|------------|
| **Where** | Inside the Visual Editor UI | External AI clients (Claude, Cursor, etc.) |
| **How** | Chat panel with streaming | MCP protocol over HTTP |
| **Setup** | Just an API key | MCP client configuration |
| **Use case** | Quick in-app workflow building | IDE integration, automation scripts |

See [MCP Server](/mcp) for the external integration approach.

</div>
