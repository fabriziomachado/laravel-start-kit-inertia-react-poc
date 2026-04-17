import { api } from './client'
import type {
  ApiResponse,
  CreateWorkflowPayload,
  PaginatedResponse,
  UpdateWorkflowPayload,
  Workflow,
  WorkflowRun,
} from './types'

export interface WorkflowListParams {
  page?: number
  search?: string
  sort?: 'name' | 'created_at' | 'updated_at'
  direction?: 'asc' | 'desc'
  folder_id?: number | null
  uncategorized?: boolean
  tag_id?: number | null
}

export const workflowsApi = {
  list: ({ page = 1, search, sort, direction, folder_id, uncategorized, tag_id }: WorkflowListParams = {}) => {
    const params = new URLSearchParams({ page: String(page) })
    if (search) params.set('search', search)
    if (sort) params.set('sort', sort)
    if (direction) params.set('direction', direction)
    if (folder_id != null) params.set('folder_id', String(folder_id))
    if (uncategorized) params.set('uncategorized', '1')
    if (tag_id != null) params.set('tag_id', String(tag_id))
    return api.get<PaginatedResponse<Workflow>>(`/workflows?${params}`)
  },

  show: (id: number) =>
    api.get<ApiResponse<Workflow>>(`/workflows/${id}`),

  create: (data: CreateWorkflowPayload) =>
    api.post<ApiResponse<Workflow>>('/workflows', data),

  update: (id: number, data: UpdateWorkflowPayload) =>
    api.put<ApiResponse<Workflow>>(`/workflows/${id}`, data),

  destroy: (id: number) =>
    api.delete<void>(`/workflows/${id}`),

  activate: (id: number) =>
    api.post<ApiResponse<Workflow>>(`/workflows/${id}/activate`),

  deactivate: (id: number) =>
    api.post<ApiResponse<Workflow>>(`/workflows/${id}/deactivate`),

  execute: (id: number, payload: Record<string, unknown>) =>
    api.post<ApiResponse<unknown>>(`/workflows/${id}/run`, { payload }),

  duplicate: (id: number) =>
    api.post<ApiResponse<Workflow>>(`/workflows/${id}/duplicate`),

  validate: (id: number) =>
    api.post<{ valid: boolean; errors: string[] }>(`/workflows/${id}/validate`),

  testNode: (id: number, nodeId: number, payload?: Record<string, unknown>) =>
    api.post<ApiResponse<WorkflowRun>>(`/workflows/${id}/test-node`, { node_id: nodeId, payload }),
}
