import { Bot, User, Wrench } from 'lucide-react'
import type { AiBuilderMessage } from '../../api/types'

interface Props {
  message: AiBuilderMessage
}

export function ChatMessage({ message }: Props) {
  const isUser = message.role === 'user'

  return (
    <div className={`flex gap-2 ${isUser ? 'flex-row-reverse' : ''}`}>
      <div className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full ${isUser ? 'bg-blue-600' : 'bg-gray-200 dark:bg-gray-700'}`}>
        {isUser ? <User size={12} className="text-white" /> : <Bot size={12} className="text-gray-600 dark:text-gray-300" />}
      </div>
      <div className={`max-w-[85%] space-y-1.5 ${isUser ? 'text-right' : ''}`}>
        <div
          className={`inline-block rounded-lg px-3 py-2 text-xs leading-relaxed ${
            isUser
              ? 'bg-blue-600 text-white'
              : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
          }`}
        >
          {message.content || (message.toolCalls?.length ? '' : '...')}
        </div>
        {message.toolCalls?.map((tc, i) => (
          <div
            key={i}
            className="flex items-center gap-1.5 rounded-md bg-amber-50 px-2 py-1 text-[10px] text-amber-700 dark:bg-amber-900/30 dark:text-amber-400"
          >
            <Wrench size={10} />
            <span className="font-medium">{formatToolName(tc.name)}</span>
            {tc.result && (() => {
              try {
                const parsed = JSON.parse(tc.result)
                if (parsed.error) return <span className="text-red-500"> — {parsed.error}</span>
                if (parsed.name) return <span> — {parsed.name}</span>
                if (parsed.deleted) return <span> — deleted</span>
                return null
              } catch {
                return null
              }
            })()}
          </div>
        ))}
      </div>
    </div>
  )
}

function formatToolName(name: string): string {
  return name
    .replace(/_/g, ' ')
    .replace(/Tool$/i, '')
    .replace(/([A-Z])/g, ' $1')
    .trim()
    .toLowerCase()
    .replace(/^\w/, (c) => c.toUpperCase())
}
