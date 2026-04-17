import { api } from './client'
import type { ApiResponse, WorkflowFolder } from './types'

export const foldersApi = {
  list: (tree = false) => {
    const params = tree ? '?tree=1' : ''
    return api.get<{ data: WorkflowFolder[] }>(`/folders${params}`)
  },

  create: (data: { name: string; parent_id?: number | null }) =>
    api.post<ApiResponse<WorkflowFolder>>('/folders', data),

  update: (id: number, data: { name?: string; parent_id?: number | null }) =>
    api.put<ApiResponse<WorkflowFolder>>(`/folders/${id}`, data),

  destroy: (id: number) =>
    api.delete<void>(`/folders/${id}`),
}
