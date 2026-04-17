import { api } from './client'
import type { ApiResponse, CreateEdgePayload, WorkflowEdge } from './types'

export const edgesApi = {
  create: (workflowId: number, data: CreateEdgePayload) =>
    api.post<ApiResponse<WorkflowEdge>>(`/workflows/${workflowId}/edges`, data),

  destroy: (workflowId: number, edgeId: number) =>
    api.delete<void>(`/workflows/${workflowId}/edges/${edgeId}`),
}
