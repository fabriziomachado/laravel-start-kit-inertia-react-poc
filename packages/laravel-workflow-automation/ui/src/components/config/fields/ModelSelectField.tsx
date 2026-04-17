import { useState, useEffect, useId } from 'react'
import type { ConfigSchemaField } from '../../../api/types'
import { metadataApi } from '../../../api/metadata'

interface Props {
  field: ConfigSchemaField
  value: string
  onChange: (value: string) => void
}

export function ModelSelectField({ field, value, onChange }: Props) {
  const listId = useId()
  const [models, setModels] = useState<string[]>([])
  const [loading, setLoading] = useState(true)
  const [isCustom, setIsCustom] = useState(false)

  useEffect(() => {
    metadataApi.models()
      .then((res) => {
        setModels(res.data)
        if (value && !res.data.includes(value)) {
          setIsCustom(true)
        }
      })
      .finally(() => setLoading(false))
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  if (loading) {
    return (
      <select disabled className="w-full rounded-md border border-gray-300 px-2.5 py-1.5 text-sm text-gray-400 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-500">
        <option>Loading models...</option>
      </select>
    )
  }

  if (isCustom) {
    return (
      <div className="flex gap-1.5">
        <input
          type="text"
          value={value ?? ''}
          onChange={(e) => onChange(e.target.value)}
          placeholder="App\Models\CustomModel"
          className="w-full rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
        />
        <button
          type="button"
          onClick={() => { setIsCustom(false); onChange('') }}
          className="shrink-0 rounded-md border border-gray-300 px-2 py-1.5 text-xs text-gray-500 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
          title="Switch to dropdown"
        >
          List
        </button>
      </div>
    )
  }

  return (
    <div className="flex gap-1.5">
      <div className="relative w-full">
        <input
          list={listId}
          value={value ?? ''}
          onChange={(e) => onChange(e.target.value)}
          placeholder={`Select ${field.label}`}
          className="w-full rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
        />
        <datalist id={listId}>
          {models.map((model) => (
            <option key={model} value={model} />
          ))}
        </datalist>
      </div>
      <button
        type="button"
        onClick={() => setIsCustom(true)}
        className="shrink-0 rounded-md border border-gray-300 px-2 py-1.5 text-xs text-gray-500 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
        title="Enter custom class"
      >
        Custom
      </button>
    </div>
  )
}
