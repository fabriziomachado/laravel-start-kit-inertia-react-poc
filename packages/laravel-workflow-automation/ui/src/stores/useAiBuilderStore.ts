import { create } from 'zustand'
import type { AiBuilderMessage, AiToolCall } from '../api/types'

const win = window as unknown as Record<string, unknown>

const getBaseUrl = (): string => {
  if (typeof window !== 'undefined' && win.__WORKFLOW_API_BASE_URL__) {
    return win.__WORKFLOW_API_BASE_URL__ as string
  }
  return import.meta.env.VITE_API_BASE_URL || '/workflow-engine'
}

interface AiBuilderState {
  isOpen: boolean
  messages: AiBuilderMessage[]
  isStreaming: boolean
  provider: string
  model: string
  error: string | null
  streamDone: boolean
  apiKeyMissing: boolean

  open: () => void
  close: () => void
  setProvider: (p: string) => void
  setModel: (m: string) => void
  sendPrompt: (workflowId: number, prompt: string) => Promise<void>
  checkApiKey: () => Promise<void>
  reset: () => void
}

export const useAiBuilderStore = create<AiBuilderState>((set, get) => ({
  isOpen: false,
  messages: [],
  isStreaming: false,
  provider: '',
  model: '',
  error: null,
  streamDone: false,
  apiKeyMissing: false,

  open: () => set({ isOpen: true }),
  close: () => set({ isOpen: false }),
  setProvider: (provider) => {
    set({ provider, model: '' })
    get().checkApiKey()
  },
  setModel: (model) => set({ model }),
  reset: () => set({ messages: [], isStreaming: false, error: null, streamDone: false }),

  checkApiKey: async () => {
    try {
      const baseUrl = getBaseUrl()
      const { provider } = get()
      const query = provider ? `?provider=${encodeURIComponent(provider)}` : ''
      const headers: Record<string, string> = {}
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      if (csrf) headers['X-CSRF-TOKEN'] = csrf
      const token = win.__WORKFLOW_API_TOKEN__ as string | undefined
      if (token) headers['Authorization'] = `Bearer ${token}`

      const res = await fetch(`${baseUrl}/ai-builder/status${query}`, { headers, credentials: 'same-origin' })
      if (res.ok) {
        const data = await res.json()
        set({ apiKeyMissing: !data.has_api_key })
      }
    } catch {
      // silently ignore
    }
  },

  sendPrompt: async (workflowId: number, prompt: string) => {
    const { provider, model } = get()

    set((s) => ({
      messages: [...s.messages, { role: 'user' as const, content: prompt }],
      isStreaming: true,
      error: null,
      streamDone: false,
    }))

    // Add an empty assistant message to accumulate streamed text
    set((s) => ({
      messages: [...s.messages, { role: 'assistant' as const, content: '', toolCalls: [] }],
    }))

    try {
      const baseUrl = getBaseUrl()
      const url = `${baseUrl}/workflows/${workflowId}/ai-build`

      const headers: Record<string, string> = {
        Accept: 'text/event-stream, application/json',
        'Content-Type': 'application/json',
      }

      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      if (csrf) headers['X-CSRF-TOKEN'] = csrf

      const token = win.__WORKFLOW_API_TOKEN__ as string | undefined
      if (token) headers['Authorization'] = `Bearer ${token}`

      const body: Record<string, string> = { prompt }
      if (provider) body.provider = provider
      if (model) body.model = model

      const res = await fetch(url, {
        method: 'POST',
        headers,
        body: JSON.stringify(body),
        credentials: 'same-origin',
      })

      if (!res.ok) {
        const text = await res.text()
        let errorMsg = `HTTP ${res.status}`
        try {
          const json = JSON.parse(text)
          errorMsg = json.message || errorMsg
        } catch {
          // If response is HTML (e.g. Laravel error page), extract title or show generic message
          if (text.includes('<!DOCTYPE') || text.includes('<html')) {
            const titleMatch = text.match(/<title>(.*?)<\/title>/)
            errorMsg = titleMatch?.[1] || `Server Error (${res.status})`
          } else if (text) {
            errorMsg = text.slice(0, 200)
          }
        }
        set({ isStreaming: false, error: errorMsg })
        updateLastAssistantMessage(set, `Error: ${errorMsg}`)
        return
      }

      const reader = res.body?.getReader()
      if (!reader) {
        set({ isStreaming: false, error: 'No response stream' })
        return
      }

      const decoder = new TextDecoder()
      let buffer = ''

      while (true) {
        const { done, value } = await reader.read()
        if (done) break

        buffer += decoder.decode(value, { stream: true })
        const lines = buffer.split('\n')
        buffer = lines.pop() ?? ''

        for (const line of lines) {
          if (!line.trim()) continue
          processSSELine(line, set, get)
        }
      }

      // Process remaining buffer
      if (buffer.trim()) {
        processSSELine(buffer, set, get)
      }

      set({ isStreaming: false, streamDone: true })
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Stream failed'
      set({ isStreaming: false, error: msg })
      updateLastAssistantMessage(set, `Error: ${msg}`)
    }
  },
}))

function updateLastAssistantMessage(
  set: (fn: (s: AiBuilderState) => Partial<AiBuilderState>) => void,
  appendText: string,
) {
  set((s) => {
    const msgs = [...s.messages]
    const last = msgs[msgs.length - 1]
    if (last?.role === 'assistant') {
      msgs[msgs.length - 1] = { ...last, content: last.content + appendText }
    }
    return { messages: msgs }
  })
}

function addToolCallToLastMessage(
  set: (fn: (s: AiBuilderState) => Partial<AiBuilderState>) => void,
  toolCall: AiToolCall,
) {
  set((s) => {
    const msgs = [...s.messages]
    const last = msgs[msgs.length - 1]
    if (last?.role === 'assistant') {
      msgs[msgs.length - 1] = {
        ...last,
        toolCalls: [...(last.toolCalls ?? []), toolCall],
      }
    }
    return { messages: msgs }
  })
}

function processSSELine(
  line: string,
  set: (fn: (s: AiBuilderState) => Partial<AiBuilderState>) => void,
  _get: () => AiBuilderState,
) {
  // Standard SSE format: "data: ..." or Vercel AI protocol
  if (line.startsWith('data: ')) {
    const data = line.slice(6)

    // Vercel AI protocol: "[DONE]"
    if (data === '[DONE]') return

    try {
      const parsed = JSON.parse(data)
      const type = parsed.type as string | undefined

      // Debug: log raw SSE events to console (remove after debugging)
      console.log('[AI SSE]', type, parsed)

      // laravel/ai standard stream event types:
      // stream_start, text_start, text_delta, text_end, tool_call, tool_result, stream_end
      // reasoning_start, reasoning_delta, reasoning_end, error
      // Vercel protocol: text-delta, tool-input-available, tool-output-available, etc.

      if (type === 'text_delta' || type === 'text-delta') {
        updateLastAssistantMessage(set, parsed.delta ?? '')
      } else if (type === 'text' || type === 'content') {
        updateLastAssistantMessage(set, parsed.text ?? parsed.content ?? '')
      } else if (type === 'tool_call' || type === 'tool-call' || type === 'tool-input-available') {
        addToolCallToLastMessage(set, {
          name: parsed.tool_name ?? parsed.toolName ?? parsed.name ?? 'unknown',
          args: parsed.arguments ?? parsed.input ?? parsed.args ?? {},
          toolId: parsed.tool_id ?? parsed.toolCallId,
        })
      } else if (type === 'tool_result' || type === 'tool-result' || type === 'tool-output-available') {
        // Find the matching tool call and update with result, or add as new tool call
        set((s) => {
          const msgs = [...s.messages]
          const last = msgs[msgs.length - 1]
          if (last?.role === 'assistant') {
            const toolCalls = [...(last.toolCalls ?? [])]
            const toolId = parsed.tool_id ?? parsed.toolCallId
            const matchIdx = toolId
              ? toolCalls.findIndex((tc) => tc.toolId === toolId)
              : -1
            const resultStr = typeof parsed.result === 'string'
              ? parsed.result
              : JSON.stringify(parsed.result ?? parsed.output ?? '')

            if (matchIdx >= 0) {
              toolCalls[matchIdx] = { ...toolCalls[matchIdx], result: resultStr }
            } else if (toolCalls.length > 0) {
              // No ID match — update the last tool call without a result
              let idx = toolCalls.length - 1
              for (let i = toolCalls.length - 1; i >= 0; i--) {
                if (!toolCalls[i].result) { idx = i; break }
              }
              toolCalls[idx] = { ...toolCalls[idx], result: resultStr }
            } else {
              // No prior tool_call event — add it directly
              toolCalls.push({
                name: parsed.tool_name ?? parsed.toolName ?? 'tool',
                args: parsed.arguments ?? {},
                toolId,
                result: resultStr,
              })
            }
            msgs[msgs.length - 1] = { ...last, toolCalls }
          }
          return { messages: msgs }
        })
      } else if (type === 'error') {
        updateLastAssistantMessage(set, `\n\nError: ${parsed.message ?? parsed.errorText ?? JSON.stringify(parsed)}`)
      }
      // Silently ignore: stream_start, stream_end, text_start, text_end,
      // reasoning_start, reasoning_delta, reasoning_end, citation
    } catch {
      // Plain text chunk (not JSON)
      updateLastAssistantMessage(set, data)
    }
  } else if (line.startsWith('event: ')) {
    // Named SSE event — we rely on data lines for content
  } else if (!line.startsWith(':')) {
    // Not a comment, might be raw text
    try {
      const parsed = JSON.parse(line)
      if (typeof parsed === 'object' && parsed.text) {
        updateLastAssistantMessage(set, parsed.text)
      }
    } catch {
      // Ignore unparseable lines
    }
  }
}
