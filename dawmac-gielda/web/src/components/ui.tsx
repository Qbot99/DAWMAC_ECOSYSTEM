import { useEffect, type ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { useSession } from '../auth'

export function Loading() {
  return <div className="loading">Ładowanie…</div>
}

export function ErrorBox({ error }: { error: unknown }) {
  if (!error) return null
  const msg = error instanceof Error ? error.message : String(error)
  return <div className="notice notice-error">{msg}</div>
}

export function Empty({ children }: { children: ReactNode }) {
  return <div className="empty">{children}</div>
}

export function RequireAuth({ children, staff = false }: { children: ReactNode; staff?: boolean }) {
  const { user, loading } = useSession()
  const loc = useLocation()
  if (loading) return <Loading />
  if (!user) return <Navigate to={'/logowanie?next=' + encodeURIComponent(loc.pathname)} replace />
  if (staff && user.role === 'user') return <ErrorBox error="Ta część jest tylko dla pracowników." />
  return <>{children}</>
}

export function Modal({ title, onClose, children }: { title: string; onClose: () => void; children: ReactNode }) {
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose()
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [onClose])
  return (
    <div className="modal-backdrop" onClick={onClose}>
      <div className="modal" role="dialog" aria-label={title} onClick={(e) => e.stopPropagation()}>
        <div className="modal-head">
          <h2>{title}</h2>
          <button className="icon-btn" onClick={onClose} aria-label="Zamknij">✕</button>
        </div>
        {children}
      </div>
    </div>
  )
}
