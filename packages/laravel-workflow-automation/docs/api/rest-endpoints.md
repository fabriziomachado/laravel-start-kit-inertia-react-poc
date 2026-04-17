# REST Endpoints

All endpoints are prefixed with the configured prefix (default: `/workflow-engine`). Middleware defaults to `['api']`.

```php
// config/workflow-automation.php
'prefix'     => 'workflow-engine',
'middleware' => ['api'],
```

Set `'routes' => false` to disable all package routes.

## Workflows

### List Workflows

```http
GET /workflow-engine/workflows
```

Returns a paginated list of all workflows.

### Get Workflow

```http
GET /workflow-engine/workflows/{id}
```

Returns the workflow with its nodes and edges.

### Create Workflow

```http
POST /workflow-engine/workflows
Content-Type: application/json

{
  "name": "My Workflow",
  "description": "Optional description"
}
```

### Update Workflow

```http
PUT /workflow-engine/workflows/{id}
Content-Type: application/json

{
  "name": "Updated Name",
  "description": "New description"
}
```

### Delete Workflow

```http
DELETE /workflow-engine/workflows/{id}
```

### Activate Workflow

```http
POST /workflow-engine/workflows/{id}/activate
```

### Deactivate Workflow

```http
POST /workflow-engine/workflows/{id}/deactivate
```

### Run Workflow

```http
POST /workflow-engine/workflows/{id}/run
Content-Type: application/json

{
  "payload": [
    {"name": "Alice", "email": "alice@example.com"}
  ]
}
```

**Response:**

```json
{
  "data": {
    "id": 1,
    "workflow_id": 1,
    "status": "completed",
    "started_at": "2024-01-15T08:00:00.000000Z",
    "finished_at": "2024-01-15T08:00:01.234000Z"
  }
}
```

### Duplicate Workflow

```http
POST /workflow-engine/workflows/{id}/duplicate
```

Creates a deep copy of the workflow with all nodes and edges. The copy is inactive by default.

### Validate Workflow

```http
POST /workflow-engine/workflows/{id}/validate
```

**Response (valid):**

```json
{
  "valid": true,
  "errors": []
}
```

**Response (invalid):**

```json
{
  "valid": false,
  "errors": [
    "Workflow has no trigger node.",
    "Node 'Send Email' has no incoming edges."
  ]
}
```

## Nodes

### Create Node

```http
POST /workflow-engine/workflows/{workflowId}/nodes
Content-Type: application/json

{
  "node_key": "send_mail",
  "name": "Welcome Email",
  "config": {
    "to": "{{ item.email }}",
    "subject": "Welcome!",
    "body": "Thanks for joining."
  }
}
```

### Update Node

```http
PUT /workflow-engine/workflows/{workflowId}/nodes/{nodeId}
Content-Type: application/json

{
  "name": "Updated Name",
  "config": {
    "to": "{{ item.new_email }}",
    "subject": "Updated subject",
    "body": "Updated body."
  }
}
```

### Delete Node

```http
DELETE /workflow-engine/workflows/{workflowId}/nodes/{nodeId}
```

### Update Node Position

```http
PATCH /workflow-engine/workflows/{workflowId}/nodes/{nodeId}/position
Content-Type: application/json

{
  "position_x": 250,
  "position_y": 100
}
```

### Get Available Variables

```http
GET /workflow-engine/workflows/{workflowId}/nodes/{nodeId}/variables
```

Returns all variables available to a node's expressions — globals, upstream node outputs, and built-in functions.

**Response:**

```json
{
  "globals": [
    { "path": "item", "type": "object", "label": "Current Item" },
    { "path": "payload", "type": "object", "label": "Initial Payload" },
    { "path": "trigger", "type": "array", "label": "Trigger Output" }
  ],
  "nodes": [
    {
      "node_id": 5,
      "node_name": "HTTP Request",
      "node_key": "http_request",
      "variables": [
        { "path": "nodes.HTTP Request.main.0.http_response.status", "type": "integer", "label": "HTTP Status Code" },
        { "path": "nodes.HTTP Request.main.0.http_response.body", "type": "mixed", "label": "Response Body" }
      ]
    }
  ],
  "functions": [
    { "name": "upper", "args": "value", "label": "Uppercase" },
    { "name": "lower", "args": "value", "label": "Lowercase" }
  ]
}
```

### Pin Node Test Data

```http
POST /workflow-engine/workflows/{workflowId}/nodes/{nodeId}/pin
Content-Type: application/json
```

**From a previous run:**

```json
{
  "source": "run",
  "node_run_id": 42
}
```

**Manual data:**

```json
{
  "source": "manual",
  "input": [{"name": "Alice", "email": "alice@example.com"}],
  "output": {"main": [{"name": "Alice", "status": "processed"}]}
}
```

When a node has pinned output, it is **skipped entirely** during test runs (`executeUpTo` / node testing) — the pinned output is returned directly. When a node has pinned input, it **executes normally** but receives the pinned input instead of computed input.

::: tip
Pinned data only affects test mode. Normal workflow runs (triggers, manual `start()`) ignore pinned data completely.
:::

### Unpin Node Test Data

```http
DELETE /workflow-engine/workflows/{workflowId}/nodes/{nodeId}/pin
```

Removes pinned data from the node. The node resumes normal execution during test runs.

## Edges

### Create Edge

```http
POST /workflow-engine/workflows/{workflowId}/edges
Content-Type: application/json

{
  "source_node_id": 1,
  "source_port": "main",
  "target_node_id": 2,
  "target_port": "main"
}
```

### Delete Edge

```http
DELETE /workflow-engine/workflows/{workflowId}/edges/{edgeId}
```

## Runs

### List Runs

```http
GET /workflow-engine/workflows/{workflowId}/runs
```

Returns paginated runs for a workflow.

### Get Run Details

```http
GET /workflow-engine/runs/{runId}
```

Returns the run with all node runs.

### Cancel Run

```http
POST /workflow-engine/runs/{runId}/cancel
```

Cancels a running or waiting run.

### Resume Run

```http
POST /workflow-engine/runs/{runId}/resume
Content-Type: application/json

{
  "resume_token": "550e8400-e29b-41d4-a716-446655440000",
  "payload": {
    "approved": true,
    "comment": "Looks good"
  }
}
```

Used with `wait_resume` nodes. The `resume_token` is found in the waiting node run's output.

### Replay Run

```http
POST /workflow-engine/runs/{runId}/replay
```

Creates a new run with the same payload as the original.

### Retry Failed Run

```http
POST /workflow-engine/runs/{runId}/retry
```

Retries from the first failed node, restoring context up to that point.

### Retry Specific Node

```http
POST /workflow-engine/runs/{runId}/retry-node
Content-Type: application/json

{
  "node_id": 5
}
```

Retries a specific failed node within a run.

## Node Registry

### List Available Nodes

```http
GET /workflow-engine/registry/nodes
```

Returns all registered node types with their metadata.

**Response:**

```json
[
  {
    "key": "send_mail",
    "type": "action",
    "label": "Send Mail",
    "config_schema": [
      {"key": "to", "type": "string", "label": "Recipient", "required": true, "supports_expression": true}
    ],
    "input_ports": ["main"],
    "output_ports": ["main", "error"]
  }
]
```

### Get Node Details

```http
GET /workflow-engine/registry/nodes/{key}
```

Returns metadata for a specific node type.

## Credentials

### List Credentials

```http
GET /workflow-engine/credentials
```

Returns all credentials. The `data` field (encrypted secrets) is **never** included in the response.

**Response:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "Stripe API Key",
      "type": "bearer_token",
      "meta": null,
      "created_at": "2024-01-15T08:00:00.000000Z",
      "updated_at": "2024-01-15T08:00:00.000000Z"
    }
  ]
}
```

### Create Credential

```http
POST /workflow-engine/credentials
Content-Type: application/json

{
  "name": "Stripe API Key",
  "type": "bearer_token",
  "data": {
    "token": "sk_live_xxx"
  }
}
```

The `data` object is encrypted at rest using Laravel's `Crypt`. The fields in `data` depend on the credential type — see [Credential Types](#credential-types).

### Get Credential

```http
GET /workflow-engine/credentials/{id}
```

Returns the credential without the `data` field.

### Update Credential

```http
PUT /workflow-engine/credentials/{id}
Content-Type: application/json

{
  "name": "Updated Name",
  "data": {
    "token": "sk_live_new_xxx"
  }
}
```

### Delete Credential

```http
DELETE /workflow-engine/credentials/{id}
```

Soft-deletes the credential.

### List Credential Types

```http
GET /workflow-engine/credentials-types
```

Returns all registered credential type schemas.

**Response:**

```json
{
  "data": {
    "bearer_token": {
      "key": "bearer_token",
      "label": "Bearer Token",
      "schema": [
        {"key": "token", "type": "password", "label": "Token", "required": true}
      ]
    },
    "basic_auth": {
      "key": "basic_auth",
      "label": "Basic Auth",
      "schema": [
        {"key": "username", "type": "string", "label": "Username", "required": true},
        {"key": "password", "type": "password", "label": "Password", "required": true}
      ]
    }
  }
}
```

## Webhook

Webhook endpoints are separate from the main API prefix:

```http
POST /workflow-webhook/{uuid}
```

The `{uuid}` is auto-generated when a webhook trigger node is created. This endpoint bypasses the configured middleware and only uses `api` middleware.

Configure the prefix:

```php
// config/workflow-automation.php
'webhook_prefix' => 'workflow-webhook',
```
