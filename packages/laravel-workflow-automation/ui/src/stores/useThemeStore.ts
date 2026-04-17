import { create } from 'zustand'

type Theme = 'light' | 'dark'

const STORAGE_KEY = 'workflow-editor-theme'

function getInitialTheme(): Theme {
  const stored = localStorage.getItem(STORAGE_KEY)
  if (stored === 'dark' || stored === 'light') return stored
  return 'light'
}

function applyTheme(theme: Theme) {
  if (theme === 'dark') {
    document.documentElement.classList.add('dark')
  } else {
    document.documentElement.classList.remove('dark')
  }
}

interface ThemeState {
  theme: Theme
  toggle: () => void
}

// Apply saved theme immediately on load
applyTheme(getInitialTheme())

export const useThemeStore = create<ThemeState>((set, get) => ({
  theme: getInitialTheme(),
  toggle: () => {
    const next = get().theme === 'light' ? 'dark' : 'light'
    localStorage.setItem(STORAGE_KEY, next)
    applyTheme(next)
    set({ theme: next })
  },
}))
