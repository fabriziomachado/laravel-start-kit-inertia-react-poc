import { create } from 'zustand'
import type { Workflow, WorkflowTag, WorkflowFolder } from '../api/types'
import { workflowsApi } from '../api/workflows'
import { tagsApi } from '../api/tags'
import { foldersApi } from '../api/folders'

let workflowsListFetchGeneration = 0

function dedupeWorkflowsById(rows: Workflow[]): Workflow[] {
  const seen = new Map<number, Workflow>()
  for (const w of rows) {
    seen.set(w.id, w)
  }
  return [...seen.values()]
}

interface WorkflowListStore {
  workflows: Workflow[]
  currentPage: number
  lastPage: number
  total: number
  isLoading: boolean
  search: string
  sort: 'name' | 'created_at' | 'updated_at'
  direction: 'asc' | 'desc'

  // Tags & Folders
  tags: WorkflowTag[]
  folders: WorkflowFolder[]
  selectedTagId: number | null
  selectedFolderId: number | null | 'uncategorized'

  fetchWorkflows: (page?: number) => Promise<void>
  setSearch: (search: string) => void
  setSort: (sort: WorkflowListStore['sort'], direction: WorkflowListStore['direction']) => void
  setSelectedTagId: (id: number | null) => void
  setSelectedFolderId: (id: number | null | 'uncategorized') => void
  fetchTags: () => Promise<void>
  fetchFolders: () => Promise<void>
  createTag: (name: string, color?: string) => Promise<WorkflowTag>
  deleteTag: (id: number) => Promise<void>
  createFolder: (name: string, parentId?: number | null) => Promise<WorkflowFolder>
  deleteFolder: (id: number) => Promise<void>
  createWorkflow: (name: string, description?: string) => Promise<Workflow>
  deleteWorkflow: (id: number) => Promise<void>
  duplicateWorkflow: (id: number) => Promise<void>
  toggleActive: (id: number, currentlyActive: boolean) => Promise<void>
}

export const useWorkflowListStore = create<WorkflowListStore>((set, get) => ({
  workflows: [],
  currentPage: 1,
  lastPage: 1,
  total: 0,
  isLoading: false,
  search: '',
  sort: 'created_at',
  direction: 'desc',
  tags: [],
  folders: [],
  selectedTagId: null,
  selectedFolderId: null,

  fetchWorkflows: async (page = 1) => {
    const { search, sort, direction, selectedTagId, selectedFolderId } = get()
    const generation = ++workflowsListFetchGeneration
    set({ isLoading: true })
    try {
      const res = await workflowsApi.list({
        page,
        search: search || undefined,
        sort,
        direction,
        tag_id: selectedTagId ?? undefined,
        folder_id: selectedFolderId === 'uncategorized' ? undefined : (selectedFolderId ?? undefined),
        uncategorized: selectedFolderId === 'uncategorized' ? true : undefined,
      })
      if (generation !== workflowsListFetchGeneration) {
        return
      }
      const rows = Array.isArray(res.data) ? res.data : []
      set({
        workflows: dedupeWorkflowsById(rows),
        currentPage: res.meta.current_page,
        lastPage: res.meta.last_page,
        total: res.meta.total,
      })
    } finally {
      if (generation === workflowsListFetchGeneration) {
        set({ isLoading: false })
      }
    }
  },

  setSearch: (search) => {
    set({ search })
    get().fetchWorkflows(1)
  },

  setSort: (sort, direction) => {
    set({ sort, direction })
    get().fetchWorkflows(1)
  },

  setSelectedTagId: (id) => {
    set({ selectedTagId: id })
    get().fetchWorkflows(1)
  },

  setSelectedFolderId: (id) => {
    set({ selectedFolderId: id })
    get().fetchWorkflows(1)
  },

  fetchTags: async () => {
    const res = await tagsApi.list()
    set({ tags: res.data })
  },

  fetchFolders: async () => {
    const res = await foldersApi.list(true)
    set({ folders: res.data })
  },

  createTag: async (name, color) => {
    const res = await tagsApi.create({ name, color })
    await get().fetchTags()
    return res.data
  },

  deleteTag: async (id) => {
    await tagsApi.destroy(id)
    if (get().selectedTagId === id) set({ selectedTagId: null })
    await get().fetchTags()
    await get().fetchWorkflows(1)
  },

  createFolder: async (name, parentId) => {
    const res = await foldersApi.create({ name, parent_id: parentId })
    await get().fetchFolders()
    return res.data
  },

  deleteFolder: async (id) => {
    await foldersApi.destroy(id)
    if (get().selectedFolderId === id) set({ selectedFolderId: null })
    await get().fetchFolders()
    await get().fetchWorkflows(1)
  },

  createWorkflow: async (name, description) => {
    const res = await workflowsApi.create({ name, description, created_via: 'editor' })
    await get().fetchWorkflows(1)
    return res.data
  },

  deleteWorkflow: async (id) => {
    const { currentPage, workflows } = get()
    const wasSoleItemOnPage = workflows.length === 1
    await workflowsApi.destroy(id)
    let page = currentPage
    if (wasSoleItemOnPage && page > 1) {
      page -= 1
    }
    await get().fetchWorkflows(page)
    const s = get()
    if (s.workflows.length === 0 && s.currentPage > 1 && s.total > 0) {
      await get().fetchWorkflows(s.currentPage - 1)
    }
  },

  duplicateWorkflow: async (id) => {
    await workflowsApi.duplicate(id)
    await get().fetchWorkflows(1)
  },

  toggleActive: async (id, currentlyActive) => {
    if (currentlyActive) {
      await workflowsApi.deactivate(id)
    } else {
      await workflowsApi.activate(id)
    }
    await get().fetchWorkflows(get().currentPage)
  },
}))
