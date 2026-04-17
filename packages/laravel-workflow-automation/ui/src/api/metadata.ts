import { api } from './client'

export const metadataApi = {
  models: () =>
    api.get<{ data: string[] }>('/metadata/models'),

  modelEvents: () =>
    api.get<{ data: string[] }>('/metadata/model-events'),
}
