const win = window as unknown as Record<string, unknown>

const getBaseUrl = (): string => {
  if (typeof window !== 'undefined' && win.__WORKFLOW_API_BASE_URL__) {
    return win.__WORKFLOW_API_BASE_URL__ as string
  }
  return import.meta.env.VITE_API_BASE_URL || '/workflow-engine'
}

const getCsrfToken = (): string | null => {
  const meta = document.querySelector('meta[name="csrf-token"]')
  return meta?.getAttribute('content') ?? null
}

export class ApiError extends Error {
  status: number
  errors: Record<string, string[]> | null

  constructor(status: number, errors: Record<string, string[]> | null, message: string) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }
}

async function request<T>(method: string, path: string, body?: unknown): Promise<T> {
  const url = `${getBaseUrl()}${path}`

  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  }

  const csrf = getCsrfToken()
  if (csrf) {
    headers['X-CSRF-TOKEN'] = csrf
  }

  const token = win.__WORKFLOW_API_TOKEN__ as string | undefined
  if (token) {
    headers['Authorization'] = `Bearer ${token}`
  }

  const res = await fetch(url, {
    method,
    headers,
    body: body != null ? JSON.stringify(body) : undefined,
    credentials: 'same-origin',
    cache: 'no-store',
  })

  if (!res.ok) {
    let errors: Record<string, string[]> | null = null
    let message = `HTTP ${res.status}`
    try {
      const json = await res.json()
      errors = json.errors ?? null
      message = json.message ?? message
    } catch {
      // ignore parse failures
    }
    throw new ApiError(res.status, errors, message)
  }

  if (res.status === 204) return undefined as T

  return res.json()
}

export const api = {
  get: <T>(path: string) => request<T>('GET', path),
  post: <T>(path: string, body?: unknown) => request<T>('POST', path, body),
  put: <T>(path: string, body?: unknown) => request<T>('PUT', path, body),
  patch: <T>(path: string, body?: unknown) => request<T>('PATCH', path, body),
  delete: <T>(path: string) => request<T>('DELETE', path),
}
