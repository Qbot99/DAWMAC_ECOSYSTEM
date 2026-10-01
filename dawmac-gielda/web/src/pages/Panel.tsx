import { useState } from 'react'
import { Link } from 'react-router-dom'
import { api, qs } from '../api'
import { useSession } from '../auth'
import type { Listing, User } from '../types'
import { date, price, size, STATUS } from '../format'
import { Empty, ErrorBox, Loading } from '../components/ui'
import DecisionDialog, { type Decision } from '../components/DecisionDialog'
import { useLoad } from '../hooks'

/**
 * Warstwa pracowników: zgłoszenia, ogłoszenia, użytkownicy, log decyzji.
 * Każda decyzja wymaga uzasadnienia — dostaje je użytkownik (DSA art. 17).
 */

interface Report {
  id: number
  reason_label: string
  details: string
  status: string
  created_at: string
  listing: { id: number; title: string; status: string } | null
  reported_user: { id: number; display_name: string; email: string; status: string } | null
  reporter: { account: boolean; name: string | null; email: string | null }
  resolution_note: string | null
  handled_by: string | null
  handled_at: string | null
}

type StaffUser = User & { ban_reason: string | null; last_login_at: string | null; listings: number; reports: number }


export default function Panel() {
  const [tab, setTab] = useState<'reports' | 'listings' | 'users' | 'log'>('reports')
  const [decision, setDecision] = useState<Decision | null>(null)
  const [tick, setTick] = useState(0)
  const stats = useLoad(() => api<Record<string, number>>('/mod/stats'), [tick])
  const done = () => setTick((t) => t + 1)

  return (
    <div className="container">
      <div className="panel-head">
        <h1>Panel pracownika</h1>
        <Link to="/moje" className="small">Moje ogłoszenia</Link>
      </div>
      <p className="muted small">Na każdym ogłoszeniu masz też przyciski pracownika: edycja, zmiana statusu, usunięcie.</p>
      {stats.data && (
        <div className="stats">
          <Stat n={stats.data.open_reports} label="Otwarte zgłoszenia" warn={stats.data.open_reports > 0} />
          <Stat n={stats.data.sell} label="Aktywne „sprzedam”" />
          <Stat n={stats.data.buy} label="Aktywne „kupię”" />
          <Stat n={stats.data.users} label="Użytkownicy" />
          <Stat n={stats.data.new_listings_7d} label="Nowe ogłoszenia (7 dni)" />
          <Stat n={stats.data.new_users_7d} label="Nowe konta (7 dni)" />
        </div>
      )}
      <div className="tabs">
        {([['reports', 'Zgłoszenia'], ['listings', 'Ogłoszenia'], ['users', 'Użytkownicy'], ['log', 'Log decyzji']] as const).map(([k, l]) => (
          <button key={k} className={tab === k ? 'tab active' : 'tab'} onClick={() => setTab(k)}>{l}</button>
        ))}
      </div>
      {tab === 'reports' && <Reports key={tick} decide={setDecision} />}
      {tab === 'listings' && <Listings key={tick} decide={setDecision} />}
      {tab === 'users' && <Users key={tick} decide={setDecision} />}
      {tab === 'log' && <Log key={tick} />}
      {decision && <DecisionDialog d={decision} onClose={() => setDecision(null)} onDone={() => (setDecision(null), done())} />}
      <p className="muted small">
        Decyzje podejmujemy wyłącznie na podstawie regulaminu i prawa — nigdy dlatego, że ogłoszenie konkuruje z ofertą DAWMAC.
      </p>
    </div>
  )
}

function Stat({ n, label, warn }: { n: number; label: string; warn?: boolean }) {
  return (
    <div className={'card stat' + (warn ? ' warn' : '')}>
      <b>{n}</b>
      <span>{label}</span>
    </div>
  )
}

function Reports({ decide }: { decide: (d: Decision) => void }) {
  const [status, setStatus] = useState('open')
  const { data, error, loading } = useLoad(() => api<{ items: Report[] }>('/mod/reports' + qs({ status })), [status])
  const resolve = (r: Report, action: string, title: string, confirm: string) =>
    decide({
      title,
      confirm,
      hint: action === 'dismiss' ? 'Uzasadnienie (dostanie je zgłaszający)' : 'Uzasadnienie — dostanie je autor i zgłaszający',
      run: (reason) => api(`/mod/reports/${r.id}`, { json: { action, reason } }),
    })

  return (
    <>
      <div className="row">
        {['open', 'resolved', 'dismissed'].map((s) => (
          <button key={s} className={'chip-btn' + (status === s ? ' active' : '')} onClick={() => setStatus(s)}>
            {{ open: 'Otwarte', resolved: 'Uwzględnione', dismissed: 'Odrzucone' }[s]}
          </button>
        ))}
      </div>
      <ErrorBox error={error} />
      {loading ? <Loading /> : !data?.items.length ? <Empty>Brak zgłoszeń.</Empty> : (
        <div className="stack">
          {data.items.map((r) => (
            <div key={r.id} className="card pad report">
              <div className="row spread">
                <b>#{r.id} · {r.reason_label}</b>
                <span className="muted small">{date(r.created_at)}</span>
              </div>
              {r.listing && (
                <div className="small">Ogłoszenie: <Link to={`/ogloszenie/${r.listing.id}`} target="_blank">{r.listing.title}</Link> ({STATUS[r.listing.status] ?? r.listing.status})</div>
              )}
              {r.reported_user && (
                <div className="small">Użytkownik: <Link to={`/uzytkownik/${r.reported_user.id}`} target="_blank">{r.reported_user.display_name}</Link> · {r.reported_user.email} {r.reported_user.status === 'banned' && <span className="chip">zablokowany</span>}</div>
              )}
              <p className="pre">{r.details}</p>
              <div className="muted small">Zgłasza: {r.reporter.name} ({r.reporter.email}){r.reporter.account ? ' · ma konto' : ' · bez konta'}</div>
              {r.status === 'open' ? (
                <div className="row">
                  {r.listing && r.listing.status !== 'removed' && (
                    <button className="btn btn-small btn-danger" onClick={() => resolve(r, 'remove_listing', 'Usuń ogłoszenie', 'Usuń ogłoszenie')}>Usuń ogłoszenie</button>
                  )}
                  {r.reported_user && r.reported_user.status === 'active' && (
                    <button className="btn btn-small btn-danger" onClick={() => resolve(r, 'ban_user', 'Zablokuj konto', 'Zablokuj konto')}>Zablokuj konto</button>
                  )}
                  <button className="btn btn-small" onClick={() => resolve(r, 'dismiss', 'Odrzuć zgłoszenie', 'Odrzuć — bez naruszenia')}>Bez naruszenia</button>
                </div>
              ) : (
                <div className="small">Decyzja ({r.handled_by}, {r.handled_at && date(r.handled_at)}): {r.resolution_note}</div>
              )}
            </div>
          ))}
        </div>
      )}
    </>
  )
}

function Listings({ decide }: { decide: (d: Decision) => void }) {
  const [q, setQ] = useState('')
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const { data, error, loading } = useLoad(() => api<{ items: Listing[] }>('/mod/listings' + qs({ q: search, status })), [search, status])
  return (
    <>
      <form className="row" onSubmit={(e) => (e.preventDefault(), setSearch(q))}>
        <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Nr, tytuł, e-mail, nazwa…" />
        <select value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">Wszystkie</option>
          {Object.entries(STATUS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
        <button className="btn">Szukaj</button>
      </form>
      <ErrorBox error={error} />
      {loading ? <Loading /> : !data?.items.length ? <Empty>Brak ogłoszeń.</Empty> : (
        <table className="table">
          <thead><tr><th></th><th>Ogłoszenie</th><th>Autor</th><th>Status</th><th>Zgł.</th><th></th></tr></thead>
          <tbody>
            {data.items.map((l) => (
              <tr key={l.id}>
                <td>{l.images[0] && <img src={l.images[0].thumb} alt="" className="tiny-thumb" />}</td>
                <td>
                  <Link to={`/ogloszenie/${l.id}`} target="_blank">#{l.id} {l.title}</Link>
                  <div className="muted small">{l.type === 'sell' ? 'Sprzedam' : 'Kupię'} · {size(l)} · {price(l)}</div>
                </td>
                <td className="small">{l.seller.display_name}<div className="muted">{l.seller.email}</div></td>
                <td className="small">{STATUS[l.status]}{l.removal_reason && <div className="muted">{l.removal_reason}</div>}</td>
                <td>{l.reports}</td>
                <td>
                  {l.status === 'removed' ? (
                    <button className="btn btn-small" onClick={() => decide({ title: 'Przywróć ogłoszenie', hint: 'Dlaczego przywracasz (dostanie to autor)', confirm: 'Przywróć', run: (reason) => api(`/mod/listings/${l.id}/restore`, { json: { reason } }) })}>Przywróć</button>
                  ) : (
                    <button className="btn btn-small btn-danger" onClick={() => decide({ title: 'Usuń ogłoszenie', hint: 'Uzasadnienie dla autora: co narusza regulamin lub prawo', confirm: 'Usuń', run: (reason) => api(`/mod/listings/${l.id}/remove`, { json: { reason } }) })}>Usuń</button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </>
  )
}

function Users({ decide }: { decide: (d: Decision) => void }) {
  const { user: me } = useSession()
  const [q, setQ] = useState('')
  const [search, setSearch] = useState('')
  const { data, error, loading, reload } = useLoad(() => api<{ items: StaffUser[] }>('/mod/users' + qs({ q: search })), [search])

  async function setRole(u: StaffUser, role: string) {
    await api(`/mod/users/${u.id}/role`, { json: { role } })
    reload()
  }

  return (
    <>
      <form className="row" onSubmit={(e) => (e.preventDefault(), setSearch(q))}>
        <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Nr, e-mail, nazwa, telefon…" />
        <button className="btn">Szukaj</button>
      </form>
      <ErrorBox error={error} />
      {loading ? <Loading /> : !data?.items.length ? <Empty>Brak użytkowników.</Empty> : (
        <table className="table">
          <thead><tr><th>Użytkownik</th><th>Typ</th><th>Ogł.</th><th>Zgł.</th><th>Status</th><th></th></tr></thead>
          <tbody>
            {data.items.map((u) => (
              <tr key={u.id}>
                <td>
                  <Link to={`/uzytkownik/${u.id}`} target="_blank">#{u.id} {u.display_name}</Link>
                  <div className="muted small">{u.email}{u.phone && ` · ${u.phone}`}</div>
                  <div className="muted small">od {u.created_at.slice(0, 10)}{u.last_login_at && ` · logowanie ${date(u.last_login_at)}`}</div>
                </td>
                <td className="small">{u.seller_type === 'company' ? `Firma: ${u.company_name}` : 'Prywatny'}{u.role !== 'user' && <div><span className="chip">{u.role}</span></div>}</td>
                <td>{u.listings}</td>
                <td>{u.reports}</td>
                <td className="small">{u.status === 'banned' ? <>Zablokowany<div className="muted">{u.ban_reason}</div></> : 'Aktywny'}</td>
                <td className="stack">
                  {u.status === 'banned' ? (
                    <button className="btn btn-small" onClick={() => decide({ title: 'Odblokuj konto', hint: 'Dlaczego odblokowujesz', confirm: 'Odblokuj', run: (reason) => api(`/mod/users/${u.id}/unban`, { json: { reason } }) })}>Odblokuj</button>
                  ) : u.role === 'user' ? (
                    <button className="btn btn-small btn-danger" onClick={() => decide({ title: 'Zablokuj konto', hint: 'Uzasadnienie dla użytkownika', confirm: 'Zablokuj', run: (reason) => api(`/mod/users/${u.id}/ban`, { json: { reason } }) })}>Zablokuj</button>
                  ) : null}
                  {me?.role === 'admin' && u.id !== me.id && u.status === 'active' && (
                    <select value={u.role} onChange={(e) => setRole(u, e.target.value)} aria-label="Rola">
                      <option value="user">użytkownik</option>
                      <option value="moderator">moderator</option>
                      <option value="admin">admin</option>
                    </select>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </>
  )
}

interface LogRow { id: number; staff_name: string; action: string; target_type: string; target_id: number; reason: string | null; created_at: string }

const ACTIONS: Record<string, string> = {
  listing_remove: 'usunął ogłoszenie', listing_edit: 'poprawił ogłoszenie', listing_restore: 'przywrócił ogłoszenie', user_ban: 'zablokował konto', user_unban: 'odblokował konto',
  report_dismiss: 'odrzucił zgłoszenie', report_remove_listing: 'uwzględnił zgłoszenie', report_ban_user: 'uwzględnił zgłoszenie (blokada)',
  user_role_user: 'odebrał rolę', user_role_moderator: 'nadał rolę moderatora', user_role_admin: 'nadał rolę admina',
}

function Log() {
  const { data, error, loading } = useLoad(() => api<{ items: LogRow[] }>('/mod/log'), [])
  return (
    <>
      <ErrorBox error={error} />
      {loading ? <Loading /> : !data?.items.length ? <Empty>Brak decyzji.</Empty> : (
        <table className="table">
          <thead><tr><th>Kiedy</th><th>Kto</th><th>Co</th><th>Uzasadnienie</th></tr></thead>
          <tbody>
            {data.items.map((r) => (
              <tr key={r.id}>
                <td className="small">{date(r.created_at)}</td>
                <td className="small">{r.staff_name}</td>
                <td className="small">{ACTIONS[r.action] ?? r.action} #{r.target_id}</td>
                <td className="small">{r.reason}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </>
  )
}
