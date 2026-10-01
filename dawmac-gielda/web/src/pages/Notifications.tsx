import { useEffect } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api'
import { useSession } from '../auth'
import type { Notification } from '../types'
import { date } from '../format'
import { Empty, ErrorBox, Loading } from '../components/ui'
import { useLoad } from '../hooks'

export default function Notifications() {
  const { refresh } = useSession()
  const { data, error, loading } = useLoad(() => api<{ items: Notification[] }>('/me/notifications'), [])

  useEffect(() => {
    if (data?.items.some((n) => !n.read)) {
      api('/me/notifications/read', { json: {} }).then(refresh)
    }
  }, [data, refresh])

  return (
    <div className="container narrow">
      <h1>Powiadomienia</h1>
      <ErrorBox error={error} />
      {loading ? (
        <Loading />
      ) : !data?.items.length ? (
        <Empty>Brak powiadomień.</Empty>
      ) : (
        <ul className="notif-list card">
          {data.items.map((n) => (
            <li key={n.id} className={n.read ? '' : 'unread'}>
              <div className="conv-top">
                <b>{n.title}</b>
                <span className="muted small">{date(n.created_at)}</span>
              </div>
              {n.body && <p className="small pre">{n.body}</p>}
              {n.link && <Link to={n.link} className="small">Otwórz →</Link>}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
