import { api } from './client'
import type { ApiResponse, PaginatedResponse, WorkflowRun } from './types'

export const runsApi = {
  list: (workflowId: number, page = 1) =>
    api.get<PaginatedResponse<WorkflowRun>>(`/workflows/${workflowId}/runs?page=${page}`),

  show: (runId: number) =>
    api.get<ApiResponse<WorkflowRun>>(`/runs/${runId}`),

  cancel: (runId: number) =>
    api.post<ApiResponse<WorkflowRun>>(`/runs/${runId}/cancel`),

  resume: (runId: number, data: { resume_token: string; payload?: Record<string, unknown> }) =>
    api.post<ApiResponse<WorkflowRun>>(`/runs/${runId}/resume`, data),

  replay: (runId: number) =>
    api.post<ApiResponse<WorkflowRun>>(`/runs/${runId}/replay`),

  retryFromFailure: (runId: number) =>
    api.post<ApiResponse<WorkflowRun>>(`/runs/${runId}/retry`),

  retryNode: (runId: number, nodeId: number) =>
    api.post<ApiResponse<WorkflowRun>>(`/runs/${runId}/retry-node`, { node_id: nodeId }),
}
