import { api } from './client'
import type {
  ApiResponse,
  Credential,
  CredentialType,
  CreateCredentialPayload,
  UpdateCredentialPayload,
} from './types'

export const credentialsApi = {
  list: () =>
    api.get<{ data: Credential[] }>('/credentials'),

  show: (id: number) =>
    api.get<ApiResponse<Credential>>(`/credentials/${id}`),

  create: (payload: CreateCredentialPayload) =>
    api.post<ApiResponse<Credential>>('/credentials', payload),

  update: (id: number, payload: UpdateCredentialPayload) =>
    api.put<ApiResponse<Credential>>(`/credentials/${id}`, payload),

  destroy: (id: number) =>
    api.delete(`/credentials/${id}`),

  types: () =>
    api.get<{ data: Record<string, CredentialType> }>('/credentials-types'),
}
