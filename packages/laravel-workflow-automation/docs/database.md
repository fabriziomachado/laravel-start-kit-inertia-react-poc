# Database Schema

The package creates 9 tables. All table names are configurable in `config/workflow-automation.php`.

## workflows

The main workflow container.

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `id` | bigint (PK) | auto | Primary key |
| `name` | varchar | — | Workflow name |
| `description` | text | null | Optional description |
| `is_active` | boolean | `false` | Whether the workflow can be triggered |
| `run_async` | boolean | `true` | Default async execution behavior |
| `settings` | json | null | Global settings (e.g. `{"retry_count": 3}`) |
| `created_via` | varchar | null | How the workflow was created (e.g. `ui`, `api`, `mcp`) |
| `folder_id` | bigint (FK) | null | Parent folder (set null on delete) |
| `created_at` | timestamp | — | — |
| `updated_at` | timestamp | — | — |
| `deleted_at` | timestamp | null | Soft delete |

## workflow_nodes

Individual steps within a workflow.

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `id` | bigint (PK) | auto | Primary key |
| `workflow_id` | bigint (FK) | — | Parent workflow (cascades on delete) |
| `type` | varchar(50) | — | Node category: `trigger`, `action`, `condition`, etc. |
| `node_key` | varchar(100) | — | Node implementation key (e.g. `send_mail`) |
| `name` | varchar | null | Display name |
| `config` | json | `{}` | Node-specific configuration |
| `pinned_data` | json | null | Pinned test data (`input`, `output`, `source_run_id`) |
| `position_x` | integer | `0` | X position for UI rendering |
| `position_y` | integer | `0` | Y position for UI rendering |
| `created_at` | timestamp | — | — |
| `updated_at` | timestamp | — | — |

**Indexes:** `(workflow_id, type)`

## workflow_edges

Connections between nodes.

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `id` | bigint (PK) | auto | Primary key |
| `workflow_id` | bigint (FK) | — | Parent workflow (cascades on delete) |
| `source_node_id` | bigint (FK) | — | Source node (cascades on delete) |
| `source_port` | varchar(50) | `'main'` | Source output port name |
| `target_node_id` | bigint (FK) | — | Target node (cascades on delete) |
| `target_port` | varchar(50) | `'main'` | Target input port name |
| `created_at` | timestamp | — | — |
| `updated_at` | timestamp | — | — |

**Indexes:** `workflow_id`, `(source_node_id, source_port)`

## workflow_runs

Execution records for each workflow invocation.

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `id` | bigint (PK) | auto | Primary key |
| `workflow_id` | bigint (FK) | — | Which workflow was executed |
| `status` | varchar(20) | `'pending'` | `pending`, `running`, `waiting`, `completed`, `failed`, `cancelled` |
| `trigger_node_id` | bigint (FK) | null | Which trigger started the run |
| `initial_payload` | json | null | Original payload passed to `start()` |
| `context` | json | null | Serialized node outputs (for resume) |
| `error_message` | text | null | Error message if failed |
| `started_at` | timestamp | null | When execution began |
| `finished_at` | timestamp | null | When execution ended |
| `created_at` | timestamp | — | — |
| `updated_at` | timestamp | — | — |

**Indexes:** `(workflow_id, status)`, `created_at`

## workflow_node_runs

Per-node execution logs within a run.

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `id` | bigint (PK) | auto | Primary key |
| `workflow_run_id` | bigint (FK) | — | Parent run (cascades on delete) |
| `node_id` | bigint (FK) | — | Which node was executed |
| `status` | varchar(20) | `'pending'` | `pending`, `running`, `completed`, `failed`, `skipped` |
| `input` | json | null | Items received by the node |
| `output` | json | null | Items produced, keyed by port |
| `error_message` | text | null | Error details |
| `duration_ms` | unsigned int | null | Execution time in milliseconds |
| `attempts` | unsigned int | `0` | Number of execution attempts |
| `executed_at` | timestamp | null | When the node started executing |
| `created_at` | timestamp | — | — |
| `updated_at` | timestamp | — | — |

**Indexes:** `(workflow_run_id, node_id)`

## workflow_credentials

Encrypted credential storage for sensitive values (API keys, tokens, passwords).

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `id` | bigint (PK) | auto | Primary key |
| `name` | varchar | — | Display name (e.g. "Stripe API Key") |
| `type` | varchar | — | Credential type key (e.g. `bearer_token`, `basic_auth`) |
| `data` | text | — | Encrypted JSON (AES-256-CBC via Laravel's `Crypt`) |
| `meta` | json | null | Non-secret metadata |
| `created_at` | timestamp | — | — |
| `updated_at` | timestamp | — | — |
| `deleted_at` | timestamp | null | Soft delete |

**Indexes:** `type`

::: warning
The `data` column is encrypted at rest using your `APP_KEY`. Never expose this column directly — use the `WorkflowCredential` model which handles encryption/decryption automatically.
:::

## workflow_tags

Tags for categorizing workflows.

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `id` | bigint (PK) | auto | Primary key |
| `name` | varchar | — | Tag name (unique) |
| `color` | varchar(7) | null | Hex color code (e.g. `#6366f1`) |
| `created_at` | timestamp | — | — |
| `updated_at` | timestamp | — | — |

**Indexes:** unique `name`

## workflow_tag_pivot

Many-to-many relationship between workflows and tags.

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `id` | bigint (PK) | auto | Primary key |
| `workflow_id` | bigint (FK) | — | Workflow (cascades on delete) |
| `tag_id` | bigint (FK) | — | Tag (cascades on delete) |

**Indexes:** unique `(workflow_id, tag_id)`

## workflow_folders

Hierarchical folder structure for organizing workflows.

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `id` | bigint (PK) | auto | Primary key |
| `name` | varchar | — | Folder name |
| `parent_id` | bigint (FK) | null | Parent folder for nesting (cascades on delete) |
| `created_at` | timestamp | — | — |
| `updated_at` | timestamp | — | — |

## Relationships

```
workflows  ─┬─ workflow_nodes
            ├─ workflow_edges
            ├─ workflow_runs ── workflow_node_runs
            └─ workflow_tag_pivot ── workflow_tags

workflow_credentials (standalone, referenced by node config via credential_id)
workflow_folders (self-referencing via parent_id, referenced by workflows.folder_id)
```

- Deleting a workflow cascades to nodes, edges, runs (and their node runs), and tag pivot entries
- Deleting a node cascades to its edges
- Deleting a run cascades to its node runs
- Deleting a tag cascades its pivot entries
- Deleting a folder cascades to child folders; workflows in that folder get `folder_id` set to null
- Credentials are standalone — deleting a credential does not affect nodes that reference it

## Custom Table Names

Change before running migrations:

```php
// config/workflow-automation.php
'tables' => [
    'workflows'    => 'custom_workflows',
    'nodes'        => 'custom_workflow_nodes',
    'edges'        => 'custom_workflow_edges',
    'runs'         => 'custom_workflow_runs',
    'node_runs'    => 'custom_workflow_node_runs',
    'credentials'  => 'custom_workflow_credentials',
    'tags'         => 'custom_workflow_tags',
    'tag_pivot'    => 'custom_workflow_tag_pivot',
    'folders'      => 'custom_workflow_folders',
],
```
