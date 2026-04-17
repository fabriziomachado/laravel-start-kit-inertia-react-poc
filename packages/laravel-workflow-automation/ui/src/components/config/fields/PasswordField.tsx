import { useState } from 'react'
import { Eye, EyeOff } from 'lucide-react'
import type { ConfigSchemaField } from '../../../api/types'

interface Props {
  field: ConfigSchemaField
  value: string
  onChange: (value: string) => void
}

export function PasswordField({ field, value, onChange }: Props) {
  const [visible, setVisible] = useState(false)

  return (
    <div className="relative">
      <input
        type={visible ? 'text' : 'password'}
        value={value ?? ''}
        onChange={(e) => onChange(e.target.value)}
        className="w-full rounded-md border border-gray-300 px-2.5 py-1.5 pr-8 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
        placeholder={field.placeholder ?? field.label}
      />
      <button
        type="button"
        onClick={() => setVisible(!visible)}
        className="absolute top-1/2 right-2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
      >
        {visible ? <EyeOff size={14} /> : <Eye size={14} />}
      </button>
    </div>
  )
}
