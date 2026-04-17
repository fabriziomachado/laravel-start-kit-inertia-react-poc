import { useState, useRef, useCallback, useEffect } from 'react'

const win = window as unknown as Record<string, unknown>

const getBaseUrl = (): string => {
  if (typeof window !== 'undefined' && win.__WORKFLOW_API_BASE_URL__) {
    return win.__WORKFLOW_API_BASE_URL__ as string
  }
  return import.meta.env.VITE_API_BASE_URL || '/workflow-engine'
}

interface UseAudioRecorderReturn {
  isSupported: boolean
  isRecording: boolean
  isTranscribing: boolean
  error: string | null
  startRecording: () => void
  stopRecording: () => Promise<string>
}

export function useAudioRecorder(): UseAudioRecorderReturn {
  const [isSupported] = useState(() => typeof navigator?.mediaDevices?.getUserMedia === 'function')
  const [isRecording, setIsRecording] = useState(false)
  const [isTranscribing, setIsTranscribing] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const mediaRecorderRef = useRef<MediaRecorder | null>(null)
  const chunksRef = useRef<Blob[]>([])
  const resolveRef = useRef<((text: string) => void) | null>(null)

  useEffect(() => {
    return () => {
      mediaRecorderRef.current?.stop()
      mediaRecorderRef.current?.stream.getTracks().forEach((t) => t.stop())
    }
  }, [])

  const startRecording = useCallback(() => {
    setError(null)
    chunksRef.current = []

    navigator.mediaDevices
      .getUserMedia({ audio: true })
      .then((stream) => {
        const recorder = new MediaRecorder(stream, { mimeType: getSupportedMimeType() })
        mediaRecorderRef.current = recorder

        recorder.ondataavailable = (e) => {
          if (e.data.size > 0) chunksRef.current.push(e.data)
        }

        recorder.onstop = async () => {
          stream.getTracks().forEach((t) => t.stop())

          const blob = new Blob(chunksRef.current, { type: recorder.mimeType })
          chunksRef.current = []

          if (blob.size === 0) {
            setError('No audio recorded.')
            setIsRecording(false)
            resolveRef.current?.('')
            resolveRef.current = null
            return
          }

          setIsTranscribing(true)
          try {
            const text = await sendToServer(blob)
            resolveRef.current?.(text)
          } catch (err) {
            const msg = err instanceof Error ? err.message : 'Transcription failed'
            setError(msg)
            resolveRef.current?.('')
          } finally {
            setIsTranscribing(false)
            resolveRef.current = null
          }
        }

        recorder.start()
        setIsRecording(true)
      })
      .catch((err) => {
        const msg = err instanceof Error ? err.message : 'Microphone access denied'
        setError(msg)
      })
  }, [])

  const stopRecording = useCallback((): Promise<string> => {
    return new Promise((resolve) => {
      const recorder = mediaRecorderRef.current
      if (!recorder || recorder.state !== 'recording') {
        resolve('')
        return
      }
      resolveRef.current = resolve
      recorder.stop()
      setIsRecording(false)
    })
  }, [])

  return { isSupported, isRecording, isTranscribing, error, startRecording, stopRecording }
}

function getSupportedMimeType(): string {
  const types = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4']
  for (const type of types) {
    if (MediaRecorder.isTypeSupported(type)) return type
  }
  return ''
}

async function sendToServer(blob: Blob): Promise<string> {
  const baseUrl = getBaseUrl()
  const url = `${baseUrl}/transcribe`

  const formData = new FormData()
  const ext = blob.type.includes('webm') ? 'webm' : blob.type.includes('ogg') ? 'ogg' : 'mp4'
  formData.append('audio', blob, `recording.${ext}`)

  const headers: Record<string, string> = {}
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  if (csrf) headers['X-CSRF-TOKEN'] = csrf
  const token = (window as unknown as Record<string, unknown>).__WORKFLOW_API_TOKEN__ as string | undefined
  if (token) headers['Authorization'] = `Bearer ${token}`

  const res = await fetch(url, {
    method: 'POST',
    headers,
    body: formData,
    credentials: 'same-origin',
  })

  if (!res.ok) {
    const text = await res.text()
    let msg = `HTTP ${res.status}`
    try {
      const json = JSON.parse(text)
      msg = json.message || msg
    } catch {
      // ignore
    }
    throw new Error(msg)
  }

  const json = await res.json()
  return json.text ?? ''
}
