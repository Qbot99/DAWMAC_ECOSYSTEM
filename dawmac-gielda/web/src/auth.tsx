import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react'
import { api } from './api'
import type { AppConfig, User } from './types'

interface Session {
  user: User | null
  loading: boolean
  unreadNotifications: number
  unreadConversations: number
  openReports: number
  config: AppConfig | null
  refresh: () => Promise<void>
  setUser: (u: User | null) => void
}

const Ctx = createContext<Session | null>(null)

interface MeResponse {
  user: User | null
  unread_notifications?: number
  unread_conversations?: number
  open_reports?: number
}

export function SessionProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)
  const [unreadNotifications, setUN] = useState(0)
  const [unreadConversations, setUC] = useState(0)
  const [openReports, setOR] = useState(0)
  const [config, setConfig] = useState<AppConfig | null>(null)

  const refresh = useCallback(async () => {
    try {
      const me = await api<MeResponse>('/me')
      setUser(me.user)
      setUN(me.unread_notifications ?? 0)
      setUC(me.unread_conversations ?? 0)
      setOR(me.open_reports ?? 0)
    } catch {
      // brak sieci — zostaw poprzedni stan
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    refresh()
    api<AppConfig>('/config').then(setConfig).catch(() => {})
    // Licznik wiadomości odświeżamy co minutę i po powrocie do karty.
    const t = setInterval(refresh, 60_000)
    const onFocus = () => document.visibilityState === 'visible' && refresh()
    document.addEventListener('visibilitychange', onFocus)
    return () => {
      clearInterval(t)
      document.removeEventListener('visibilitychange', onFocus)
    }
  }, [refresh])

  return (
    <Ctx.Provider value={{ user, loading, unreadNotifications, unreadConversations, openReports, config, refresh, setUser }}>
      {children}
    </Ctx.Provider>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export function useSession(): Session {
  const s = useContext(Ctx)
  if (!s) throw new Error('useSession poza SessionProvider')
  return s
}
