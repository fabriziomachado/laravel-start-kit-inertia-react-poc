import { useState, useRef, useCallback, useEffect } from 'react'

interface SpeechRecognitionEvent {
  results: SpeechRecognitionResultList
  resultIndex: number
}

interface SpeechRecognitionErrorEvent {
  error: string
}

type SpeechRecognitionInstance = {
  lang: string
  continuous: boolean
  interimResults: boolean
  start: () => void
  stop: () => void
  abort: () => void
  onresult: ((event: SpeechRecognitionEvent) => void) | null
  onend: (() => void) | null
  onerror: ((event: SpeechRecognitionErrorEvent) => void) | null
}

function getSpeechRecognitionConstructor(): (new () => SpeechRecognitionInstance) | null {
  const win = window as unknown as Record<string, unknown>
  return (win.SpeechRecognition ?? win.webkitSpeechRecognition ?? null) as
    | (new () => SpeechRecognitionInstance)
    | null
}

const ERROR_MESSAGES: Record<string, string> = {
  'no-speech': 'No speech detected. Check your microphone.',
  'audio-capture': 'No microphone found.',
  'not-allowed': 'Microphone access denied.',
  'network': 'Network error. Speech recognition requires internet.',
}

interface UseSpeechRecognitionReturn {
  isSupported: boolean
  isListening: boolean
  liveText: string
  finalText: string
  error: string | null
  startListening: () => void
  stopListening: () => void
  clearFinalText: () => void
}

export function useSpeechRecognition(lang?: string): UseSpeechRecognitionReturn {
  const [isSupported] = useState(() => getSpeechRecognitionConstructor() !== null)
  const [isListening, setIsListening] = useState(false)
  const [liveText, setLiveText] = useState('')
  const [finalText, setFinalText] = useState('')
  const [error, setError] = useState<string | null>(null)
  const recognitionRef = useRef<SpeechRecognitionInstance | null>(null)
  const latestRef = useRef('')

  useEffect(() => {
    return () => {
      recognitionRef.current?.abort()
    }
  }, [])

  const startListening = useCallback(() => {
    const Ctor = getSpeechRecognitionConstructor()
    if (!Ctor) return

    console.log('[STT] Starting...')
    recognitionRef.current?.abort()
    latestRef.current = ''
    setLiveText('')
    setFinalText('')
    setError(null)

    const recognition = new Ctor()
    recognition.lang = lang || navigator.languages?.[0] || navigator.language || 'en-US'
    recognition.continuous = true
    recognition.interimResults = true
    recognitionRef.current = recognition

    recognition.onresult = (event: SpeechRecognitionEvent) => {
      let text = ''
      for (let i = 0; i < event.results.length; i++) {
        text += event.results[i][0].transcript
      }
      console.log('[STT] onresult:', JSON.stringify(text))
      latestRef.current = text
      setLiveText(text)
    }

    recognition.onend = () => {
      console.log('[STT] onend, text:', JSON.stringify(latestRef.current))
      if (latestRef.current) {
        setFinalText(latestRef.current)
      }
      setLiveText('')
      latestRef.current = ''
      setIsListening(false)
    }

    recognition.onerror = (event: SpeechRecognitionErrorEvent) => {
      console.log('[STT] onerror:', event.error)
      if (event.error !== 'aborted') {
        setError(ERROR_MESSAGES[event.error] ?? `Speech error: ${event.error}`)
        if (latestRef.current) {
          setFinalText(latestRef.current)
        }
        setLiveText('')
        latestRef.current = ''
        setIsListening(false)
      }
    }

    recognition.start()
    setIsListening(true)
  }, [])

  const stopListening = useCallback(() => {
    console.log('[STT] Stopping...')
    recognitionRef.current?.stop()
  }, [])

  const clearFinalText = useCallback(() => {
    setFinalText('')
  }, [])

  return { isSupported, isListening, liveText, finalText, error, startListening, stopListening, clearFinalText }
}
