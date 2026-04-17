import { useEffect, useRef, useState } from 'react'
import { X, Send, Bot, Loader2, Trash2, Mic, Square, AlertTriangle } from 'lucide-react'
import { useAiBuilderStore } from '../../stores/useAiBuilderStore'
import { useRegistryStore } from '../../stores/useRegistryStore'
import { useAudioRecorder } from '../../hooks/useAudioRecorder'
import { ChatMessage } from './ChatMessage'

interface Props {
  workflowId: number
  onStreamDone: () => void
}

export function AiBuilderPanel({ workflowId, onStreamDone }: Props) {
  const {
    messages,
    isStreaming,
    provider,
    model,
    error,
    streamDone,
    apiKeyMissing,
    close,
    setProvider,
    setModel,
    sendPrompt,
    checkApiKey,
    reset,
  } = useAiBuilderStore()

  const [input, setInput] = useState('')
  const messagesEndRef = useRef<HTMLDivElement>(null)
  const textareaRef = useRef<HTMLTextAreaElement>(null)
  const streamDoneHandled = useRef(false)
  const { isSupported: micSupported, isRecording, isTranscribing, error: micError, startRecording, stopRecording } = useAudioRecorder()

  // Get provider/model options from the AI node's config schema in the registry
  const registryNodes = useRegistryStore((s) => s.nodes)
  const aiNode = registryNodes.find((n) => n.key === 'ai')
  const providerField = aiNode?.config_schema.find((f) => f.key === 'provider')
  const modelField = aiNode?.config_schema.find((f) => f.key === 'model')
  const providers = providerField?.options ?? []
  const modelsForProvider = modelField?.options_map?.[provider] ?? []

  // Auto-scroll to bottom on new messages
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages])

  // Reset the guard when a new stream starts
  useEffect(() => {
    if (isStreaming) {
      streamDoneHandled.current = false
    }
  }, [isStreaming])

  // Notify parent when stream completes (guard prevents double-firing)
  useEffect(() => {
    if (streamDone && !streamDoneHandled.current) {
      streamDoneHandled.current = true
      useAiBuilderStore.setState({ streamDone: false })
      onStreamDone()
    }
  }, [streamDone, onStreamDone])

  // Check API key on open
  useEffect(() => {
    checkApiKey()
  }, [checkApiKey])

  // Focus textarea on open
  useEffect(() => {
    textareaRef.current?.focus()
  }, [])

  const handleMicToggle = async () => {
    if (isRecording) {
      const text = await stopRecording()
      if (text) {
        setInput((prev) => {
          const separator = prev && !prev.endsWith(' ') ? ' ' : ''
          return prev + separator + text
        })
        requestAnimationFrame(autoResize)
      }
    } else {
      startRecording()
    }
  }

  const handleSend = () => {
    const trimmed = input.trim()
    if (!trimmed || isStreaming) return
    setInput('')
    requestAnimationFrame(autoResize)
    sendPrompt(workflowId, trimmed)
  }

  const autoResize = () => {
    const el = textareaRef.current
    if (!el) return
    el.style.height = 'auto'
    el.style.height = Math.min(el.scrollHeight, 150) + 'px'
  }

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      handleSend()
    }
  }


  return (
    <div className="flex h-full flex-col">
      {/* Header */}
      <div className="flex items-center justify-between border-b border-gray-200 px-3 py-2.5 dark:border-gray-700">
        <div className="flex items-center gap-2">
          <Bot size={14} className="text-purple-600 dark:text-purple-400" />
          <span className="text-xs font-semibold text-gray-900 dark:text-gray-100">AI Builder</span>
        </div>
        <div className="flex items-center gap-1">
          {messages.length > 0 && (
            <button
              onClick={reset}
              className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-gray-700 dark:hover:text-gray-300"
              title="Clear chat"
            >
              <Trash2 size={12} />
            </button>
          )}
          <button
            onClick={close}
            className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-gray-700 dark:hover:text-gray-300"
          >
            <X size={14} />
          </button>
        </div>
      </div>

      {/* Provider / Model selectors */}
      <div className="flex gap-2 border-b border-gray-200 px-3 py-2 dark:border-gray-700">
        <select
          value={provider}
          onChange={(e) => setProvider(e.target.value)}
          className="flex-1 rounded border border-gray-300 bg-white px-2 py-1 text-[10px] text-gray-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
        >
          <option value="">Provider (default)</option>
          {providers.map((p) => (
            <option key={p} value={p}>{p}</option>
          ))}
        </select>
        <select
          value={model}
          onChange={(e) => setModel(e.target.value)}
          className="flex-1 rounded border border-gray-300 bg-white px-2 py-1 text-[10px] text-gray-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
          disabled={!provider}
        >
          <option value="">Model (default)</option>
          {modelsForProvider.map((m) => (
            <option key={m} value={m}>{m}</option>
          ))}
        </select>
      </div>

      {/* API Key Warning */}
      {apiKeyMissing && (
        <div className="mx-3 mt-2 flex items-start gap-1.5 rounded bg-amber-50 px-2.5 py-2 text-[11px] text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
          <AlertTriangle size={12} className="mt-0.5 shrink-0" />
          <span>
            API key for <strong>{provider || 'default provider'}</strong> is not configured. Add it to your <code className="rounded bg-amber-100 px-1 dark:bg-amber-900/50">.env</code> file.
          </span>
        </div>
      )}

      {/* Messages */}
      <div className="flex-1 overflow-y-auto px-3 py-3 space-y-3">
        {messages.length === 0 && (
          <div className="flex flex-col items-center justify-center py-8 text-center">
            <Bot size={24} className="mb-2 text-gray-300 dark:text-gray-600" />
            <p className="text-xs text-gray-400 dark:text-gray-500">
              Describe the workflow you want to build
            </p>
            <p className="mt-1 text-[10px] text-gray-300 dark:text-gray-600">
              e.g. "When a user registers, send a welcome email"
            </p>
          </div>
        )}
        {messages.map((msg, i) => (
          <ChatMessage key={i} message={msg} />
        ))}
        {isStreaming && (
          <div className="flex items-center gap-1.5 text-[10px] text-gray-400 dark:text-gray-500">
            <Loader2 size={10} className="animate-spin" />
            Building workflow...
          </div>
        )}
        <div ref={messagesEndRef} />
      </div>

      {/* Error */}
      {error && (
        <div className="mx-3 mb-2 rounded bg-red-50 px-2 py-1.5 text-[10px] text-red-600 dark:bg-red-900/30 dark:text-red-400">
          {error}
        </div>
      )}
      {micError && (
        <div className="mx-3 mb-2 rounded bg-yellow-50 px-2 py-1.5 text-[10px] text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
          {micError}
        </div>
      )}

      {/* Input */}
      <div className="border-t border-gray-200 p-3 dark:border-gray-700">
        <div className="flex gap-2">
          <textarea
            ref={textareaRef}
            value={input}
            onChange={(e) => { setInput(e.target.value); autoResize() }}
            onKeyDown={handleKeyDown}
            placeholder={isTranscribing ? 'Transcribing...' : 'Describe your workflow...'}
            disabled={isStreaming || isTranscribing}
            rows={1}
            className="flex-1 resize-none rounded-md border border-gray-300 px-2.5 py-1.5 text-xs text-gray-900 placeholder-gray-400 focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-500"
          />
          {micSupported && !isStreaming && (
            <button
              type="button"
              onClick={handleMicToggle}
              disabled={isTranscribing}
              className={`self-end rounded-md p-2 ${
                isRecording
                  ? 'animate-pulse bg-red-500 text-white'
                  : isTranscribing
                    ? 'bg-gray-200 text-gray-400 dark:bg-gray-600 dark:text-gray-500'
                    : 'bg-gray-200 text-gray-600 hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-300 dark:hover:bg-gray-500'
              }`}
              title={isRecording ? 'Stop recording' : isTranscribing ? 'Transcribing...' : 'Voice input'}
            >
              {isRecording ? <Square size={14} /> : isTranscribing ? <Loader2 size={14} className="animate-spin" /> : <Mic size={14} />}
            </button>
          )}
          <button
            onClick={handleSend}
            disabled={isStreaming || !input.trim()}
            className="self-end rounded-md bg-purple-600 p-2 text-white hover:bg-purple-700 disabled:opacity-50"
          >
            {isStreaming ? <Loader2 size={14} className="animate-spin" /> : <Send size={14} />}
          </button>
        </div>
      </div>
    </div>
  )
}
