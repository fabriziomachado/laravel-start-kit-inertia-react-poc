// ── API Response Types (matches Laravel Resources exactly) ──

export interface WorkflowTag {
  id: number
  name: string
  color: string | null
  workflows_count?: number
  created_at: string
  updated_at: string
}

export interface WorkflowFolder {
  id: number
  name: string
  parent_id: number | null
  children?: WorkflowFolder[]
  workflows_count?: number
  created_at: string
  updated_at: string
}

export interface Workflow {
  id: number
  name: string
  description: string | null
  is_active: boolean
  run_async: boolean
  settings: Record<string, unknown> | null
  created_via: CreatedVia | null
  folder_id: number | null
  folder?: WorkflowFolder | null
  tags?: WorkflowTag[]
  created_at: string
  updated_at: string
  nodes?: WorkflowNode[]
  edges?: WorkflowEdge[]
}

export interface PinnedData {
  input?: Record<string, unknown>[]
  output?: Record<string, Record<string, unknown>[]>
  source_run_id?: number | null
}

export interface WorkflowNode {
  id: number
  workflow_id: number
  type: NodeType
  node_key: string
  name: string | null
  config: Record<string, unknown> | null
  pinned_data: PinnedData | null
  position_x: number | null
  position_y: number | null
  created_at: string
  updated_at: string
}

export interface WorkflowEdge {
  id: number
  workflow_id: number
  source_node_id: number
  target_node_id: number
  source_port: string
  target_port: string
  created_at: string
  updated_at: string
}

export interface WorkflowRun {
  id: number
  workflow_id: number
  status: RunStatus
  trigger_node_id: number | null
  initial_payload: Record<string, unknown> | null
  error_message: string | null
  duration_ms: number | null
  started_at: string | null
  finished_at: string | null
  created_at: string
  updated_at: string
  node_runs?: WorkflowNodeRun[]
}

export interface WorkflowNodeRun {
  id: number
  workflow_run_id: number
  node_id: number
  status: NodeRunStatus
  input: Record<string, unknown> | null
  output: Record<string, unknown> | null
  error_message: string | null
  duration_ms: number | null
  attempts: number | null
  executed_at: string | null
  created_at: string
  updated_at: string
}

export interface RegistryNode {
  key: string
  label: string
  type: NodeType
  input_ports: string[]
  output_ports: string[]
  config_schema: ConfigSchemaField[]
  output_schema: Record<string, OutputSchemaField[]>
  documentation: string | null
}

export interface OutputSchemaField {
  key: string
  type: string
  label: string
}

export interface AvailableVariable {
  path: string
  type: string
  label: string
  children?: AvailableVariable[]
}

export interface UpstreamNode {
  node_id: number
  node_name: string
  node_key: string
  variables: AvailableVariable[]
}

export interface AvailableFunction {
  name: string
  args: string
  label: string
}

export interface AvailableVariablesResponse {
  globals: AvailableVariable[]
  nodes: UpstreamNode[]
  functions: AvailableFunction[]
}

export interface ConfigSchemaField {
  key: string
  type:
    | 'string' | 'textarea' | 'select' | 'boolean' | 'integer' | 'json'
    | 'keyvalue' | 'array_of_objects' | 'multiselect' | 'model_select'
    | 'number' | 'color' | 'url' | 'password' | 'slider' | 'code'
    | 'info' | 'section' | 'custom' | 'credential' | 'workflow_select'
  label: string
  required?: boolean
  options?: string[]
  options_from?: string
  depends_on?: string
  options_map?: Record<string, string[]>
  supports_expression?: boolean
  readonly?: boolean
  schema?: ConfigSchemaField[]
  show_when?: { key: string; value: string | string[] }
  description?: string
  placeholder?: string
  min?: number
  max?: number
  step?: number
  language?: string
  collapsible?: boolean
  collapsed?: boolean
  custom_component?: string
  credential_types?: string[]
}

export type CreatedVia = 'editor' | 'import' | 'code' | 'api' | 'duplicate'
export type NodeType = 'trigger' | 'action' | 'condition' | 'transformer' | 'control' | 'utility' | 'code' | 'annotation'
export type RunStatus = 'pending' | 'running' | 'completed' | 'failed' | 'cancelled' | 'waiting'
export type NodeRunStatus = 'pending' | 'running' | 'completed' | 'failed' | 'skipped'

export interface Credential {
  id: number
  name: string
  type: string
  meta: Record<string, unknown> | null
  created_at: string
  updated_at: string
}

export interface CredentialType {
  key: string
  label: string
  schema: ConfigSchemaField[]
}

export interface CreateCredentialPayload {
  name: string
  type: string
  data: Record<string, string>
  meta?: Record<string, unknown>
}

export interface UpdateCredentialPayload {
  name?: string
  type?: string
  data?: Record<string, string>
  meta?: Record<string, unknown>
}

// ── AI Builder ──

export interface AiBuilderMessage {
  role: 'user' | 'assistant'
  content: string
  toolCalls?: AiToolCall[]
}

export interface AiToolCall {
  name: string
  args: Record<string, unknown>
  toolId?: string
  result?: string
}

// ── Request Payloads ──

export interface CreateWorkflowPayload {
  name: string
  description?: string
  is_active?: boolean
  created_via?: CreatedVia
  folder_id?: number | null
  tag_ids?: number[]
}

export interface UpdateWorkflowPayload {
  name?: string
  description?: string
  folder_id?: number | null
  tag_ids?: number[]
  settings?: Record<string, unknown> | null
}

export interface CreateNodePayload {
  node_key: string
  label?: string
  config?: Record<string, unknown>
  position_x?: number
  position_y?: number
}

export interface UpdateNodePayload {
  label?: string
  config?: Record<string, unknown>
}

export interface UpdateNodePositionPayload {
  position_x: number
  position_y: number
}

export interface CreateEdgePayload {
  source_node_id: number
  target_node_id: number
  source_port?: string
  target_port?: string
}

// ── Pagination ──

export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  links: {
    first: string
    last: string
    prev: string | null
    next: string | null
  }
}

export interface ApiResponse<T> {
  data: T
}
