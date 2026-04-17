import { useState } from 'react'
import { createPortal } from 'react-dom'
import type { Credential, CredentialType } from '../../api/types'
import { credentialsApi } from '../../api/credentials'
import { DynamicForm } from '../config/DynamicForm'

interface Props {
  allowedTypes: string[]
  credentialTypes: Record<string, CredentialType>
  onClose: () => void
  onCreated: (credential: Credential) => void
}

export function CredentialModal({ allowedTypes, credentialTypes, onClose, onCreated }: Props) {
  const availableTypes = allowedTypes.length > 0
    ? Object.values(credentialTypes).filter((t) => allowedTypes.includes(t.key))
    : Object.values(credentialTypes)

  const [name, setName] = useState('')
  const [selectedType, setSelectedType] = useState(availableTypes[0]?.key ?? '')
  const [data, setData] = useState<Record<string, unknown>>({})
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const typeSchema = credentialTypes[selectedType]?.schema ?? []

  const handleSave = async () => {
    if (!name.trim() || !selectedType) return

    setSaving(true)
    setError(null)

    try {
      const res = await credentialsApi.create({
        name: name.trim(),
        type: selectedType,
        data: data as Record<string, string>,
      })
      onCreated(res.data)
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Failed to create credential')
    } finally {
      setSaving(false)
    }
  }

  return createPortal(
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div className="w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800 dark:shadow-2xl dark:shadow-black/40">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">New Credential</h3>

        <div className="mt-4 space-y-3">
          <div>
            <label className="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
              Name <span className="text-red-400">*</span>
            </label>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="My API Key"
              className="w-full rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
            />
          </div>

          <div>
            <label className="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
              Type <span className="text-red-400">*</span>
            </label>
            <select
              value={selectedType}
              onChange={(e) => { setSelectedType(e.target.value); setData({}) }}
              className="w-full rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
            >
              {availableTypes.map((t) => (
                <option key={t.key} value={t.key}>{t.label}</option>
              ))}
            </select>
          </div>

          {typeSchema.length > 0 && (
            <DynamicForm
              schema={typeSchema}
              values={data}
              onChange={(key, val) => setData((prev) => ({ ...prev, [key]: val }))}
            />
          )}

          {error && (
            <p className="text-xs text-red-500">{error}</p>
          )}
        </div>

        <div className="mt-4 flex justify-end gap-2">
          <button
            onClick={onClose}
            className="rounded-md px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
          >
            Cancel
          </button>
          <button
            onClick={handleSave}
            disabled={saving || !name.trim() || !selectedType}
            className="rounded-md bg-blue-600 px-3 py-1.5 text-sm text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {saving ? 'Saving...' : 'Create'}
          </button>
        </div>
      </div>
    </div>,
    document.body
  )
}
