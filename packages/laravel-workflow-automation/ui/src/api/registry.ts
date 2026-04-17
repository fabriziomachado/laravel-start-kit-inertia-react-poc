import { api } from './client'
import type { RegistryNode } from './types'

export const registryApi = {
  list: () =>
    api.get<{ data: RegistryNode[] }>('/registry/nodes'),

  editorScripts: () =>
    api.get<{ data: { scripts: string[] } }>('/registry/editor-scripts'),
}
