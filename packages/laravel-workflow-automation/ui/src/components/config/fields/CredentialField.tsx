import { useState, useEffect } from 'react'
import type { ConfigSchemaField, Credential, CredentialType } from '../../../api/types'
import { credentialsApi } from '../../../api/credentials'
import { CredentialModal } from '../../credentials/CredentialModal'

interface Props {
  field: ConfigSchemaField
  value: number | null
  onChange: (value: number | null) => void
}

export function CredentialField({ field, value, onChange }: Props) {
  const [credentials, setCredentials] = useState<Credential[]>([])
  const [credentialTypes, setCredentialTypes] = useState<Record<string, CredentialType>>({})
  const [loading, setLoading] = useState(true)
  const [showModal, setShowModal] = useState(false)

  const allowedTypes = field.credential_types ?? []

  const loadCredentials = () => {
    credentialsApi.list()
      .then((res) => {
        const filtered = allowedTypes.length > 0
          ? res.data.filter((c) => allowedTypes.includes(c.type))
          : res.data
        setCredentials(filtered)
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    loadCredentials()
    credentialsApi.types().then((res) => setCredentialTypes(res.data))
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  const selected = credentials.find((c) => c.id === value)

  if (loading) {
    return (
      <select disabled className="w-full rounded-md border border-gray-300 px-2.5 py-1.5 text-sm text-gray-400 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-500">
        <option>Loading credentials...</option>
      </select>
    )
  }

  return (
    <>
      <div className="flex gap-1.5">
        <select
          value={value ?? ''}
          onChange={(e) => onChange(e.target.value ? Number(e.target.value) : null)}
          className="w-full rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
        >
          <option value="">No credential</option>
          {credentials.map((cred) => (
            <option key={cred.id} value={cred.id}>
              {cred.name} ({credentialTypes[cred.type]?.label ?? cred.type})
            </option>
          ))}
        </select>
        <button
          type="button"
          onClick={() => setShowModal(true)}
          className="shrink-0 rounded-md border border-gray-300 px-2 py-1.5 text-xs text-gray-500 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
          title="Create new credential"
        >
          + New
        </button>
      </div>
      {selected && (
        <p className="mt-1 text-[10px] text-gray-400 dark:text-gray-500">
          Type: {credentialTypes[selected.type]?.label ?? selected.type}
        </p>
      )}
      {showModal && (
        <CredentialModal
          allowedTypes={allowedTypes}
          credentialTypes={credentialTypes}
          onClose={() => setShowModal(false)}
          onCreated={(cred) => {
            setShowModal(false)
            loadCredentials()
            onChange(cred.id)
          }}
        />
      )}
    </>
  )
}
