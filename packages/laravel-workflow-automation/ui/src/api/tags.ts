import { api } from './client'
import type { ApiResponse, WorkflowTag } from './types'

export const tagsApi = {
  list: (search?: string) => {
    const params = new URLSearchParams()
    if (search) params.set('search', search)
    return api.get<{ data: WorkflowTag[] }>(`/tags?${params}`)
  },

  create: (data: { name: string; color?: string }) =>
    api.post<ApiResponse<WorkflowTag>>('/tags', data),

  update: (id: number, data: { name?: string; color?: string }) =>
    api.put<ApiResponse<WorkflowTag>>(`/tags/${id}`, data),

  destroy: (id: number) =>
    api.delete<void>(`/tags/${id}`),
}
