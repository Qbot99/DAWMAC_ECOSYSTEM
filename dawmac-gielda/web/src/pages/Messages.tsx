import { useEffect, useRef, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api } from '../api'
import { useSession } from '../auth'
import type { Conversation, Message } from '../types'
import { date } from '../format'
import { Empty, ErrorBox, Loading } from '../components/ui'
import { useLoad } from '../hooks'
import ReportDialog from '../components/ReportDialog'

export function Conversations() {
  const { data, error, loading } = useLoad(() => api<{ items: Conversation[] }>('/conversations'), [])
  return (
    <div className="container narrow">
      <h1>Wiadomości</h1>
      <ErrorBox error={error} />
      {loading ? (
        <Loading />
      ) : !data?.items.length ? (
        <Empty>Brak rozmów. Napisz do kogoś z poziomu ogłoszenia.</Empty>
      ) : (
        <ul className="conv-list card">
          {data.items.map((c) => (
            <li key={c.id} className={c.unread ? 'unread' : ''}>
              <Link to={`/wiadomosci/${c.id}`}>
                <div className="conv-top">
                  <b>{c.partner.display_name}</b>
                  <span className="muted small">{date(c.last_message_at)}</span>
                </div>
                <div className="small">{c.i_am_owner ? 'Twoje ogłoszenie: ' : ''}{c.listing.title}</div>
                <div className="muted small ellipsis">{c.last_message}</div>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

export function ConversationPage() {
  const { id } = useParams()
  const { refresh } = useSession()
  const { data, error, loading, reload } = useLoad(
    () => api<{ conversation: Conversation; messages: Message[] }>(`/conversations/${id}`),
    [id],
  )
  const [body, setBody] = useState('')
  const [sendError, setSendError] = useState<unknown>(null)
  const [report, setReport] = useState(false)
  const end = useRef<HTMLDivElement>(null)

  useEffect(() => {
    end.current?.scrollIntoView()
    if (data) refresh()
  }, [data, refresh])

  // Nowe wiadomości co 15 s, gdy rozmowa jest otwarta.
  useEffect(() => {
    const t = setInterval(() => document.visibilityState === 'visible' && reload(), 15_000)
    return () => clearInterval(t)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id])

  if (loading && !data) return <Loading />
  if (!data) return <div className="container"><ErrorBox error={error} /></div>

  const c = data.conversation

  async function send(e: React.FormEvent) {
    e.preventDefault()
    try {
      await api(`/conversations/${id}`, { json: { body } })
      setBody('')
      setSendError(null)
      reload()
    } catch (err) {
      setSendError(err)
    }
  }

  return (
    <div className="container narrow chat">
      <Link to="/wiadomosci" className="back">← Wiadomości</Link>
      <div className="card pad chat-head">
        <div>
          <b>{c.partner.display_name}</b>
          <div className="small">
            <Link to={`/ogloszenie/${c.listing.id}`}>{c.listing.title}</Link>
          </div>
        </div>
        <button className="link-btn danger small" onClick={() => setReport(true)}>⚑ Zgłoś</button>
      </div>
      <div className="notice notice-info small">
        Nie płać przedpłat za „kuriera” i nie klikaj w linki do płatności. Najbezpieczniej: odbiór osobisty.
      </div>
      <div className="messages">
        {data.messages.map((m) => (
          <div key={m.id} className={'msg ' + (m.mine ? 'mine' : 'theirs')}>
            <div className="bubble">{m.body}</div>
            <div className="muted tiny">{date(m.created_at)}</div>
          </div>
        ))}
        <div ref={end} />
      </div>
      {c.partner.active ? (
        <form className="chat-input" onSubmit={send}>
          <textarea value={body} onChange={(e) => setBody(e.target.value)} rows={2} placeholder="Napisz wiadomość…" required
            onKeyDown={(e) => {
              if (e.key === 'Enter' && !e.shiftKey && body.trim()) {
                e.preventDefault()
                e.currentTarget.form?.requestSubmit()
              }
            }} />
          <button className="btn btn-primary" disabled={!body.trim()}>Wyślij</button>
        </form>
      ) : (
        <div className="notice">Ta osoba nie ma już aktywnego konta.</div>
      )}
      <ErrorBox error={sendError} />
      {report && <ReportDialog userId={c.partner.id} listingId={c.listing.id} onClose={() => setReport(false)} />}
    </div>
  )
}
