import { useState } from 'react'
import { api } from '../api'
import { useSession } from '../auth'
import { ErrorBox, Modal } from './ui'
import { fieldErrors } from '../hooks'

/** Formularz „Zgłoś” (DSA art. 16) — dostępny także bez konta. */
export default function ReportDialog({ listingId, userId, onClose }: { listingId?: number; userId?: number; onClose: () => void }) {
  const { user, config } = useSession()
  const [reason, setReason] = useState('')
  const [details, setDetails] = useState('')
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [goodFaith, setGoodFaith] = useState(false)
  const [error, setError] = useState<unknown>(null)
  const [sentId, setSentId] = useState<number | null>(null)
  const [busy, setBusy] = useState(false)
  const errs = fieldErrors(error)

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setBusy(true)
    try {
      const r = await api<{ id: number }>('/reports', {
        json: { listing_id: listingId, user_id: userId, reason, details, reporter_name: name, reporter_email: email, good_faith: goodFaith },
      })
      setSentId(r.id)
    } catch (err) {
      setError(err)
    } finally {
      setBusy(false)
    }
  }

  return (
    <Modal title="Zgłoś naruszenie" onClose={onClose}>
      {sentId ? (
        <div>
          <p>Dziękujemy. Zgłoszenie nr <b>{sentId}</b> trafiło do moderacji.</p>
          <p className="muted">Potwierdzenie i decyzję wyślemy e-mailem.</p>
          <button className="btn btn-primary" onClick={onClose}>Zamknij</button>
        </div>
      ) : (
        <form onSubmit={submit} className="form">
          <label className="field">
            <span>Powód</span>
            <select value={reason} onChange={(e) => setReason(e.target.value)} required>
              <option value="">Wybierz…</option>
              {Object.entries(config?.report_reasons ?? {}).map(([k, v]) => (
                <option key={k} value={k}>{v}</option>
              ))}
            </select>
            {errs.reason && <em className="err">{errs.reason}</em>}
          </label>
          <label className="field">
            <span>Co jest nie tak? Wyjaśnij, dlaczego treść narusza prawo lub regulamin</span>
            <textarea value={details} onChange={(e) => setDetails(e.target.value)} rows={4} required minLength={10} />
            {errs.details && <em className="err">{errs.details}</em>}
          </label>
          {!user && (
            <div className="grid2">
              <label className="field">
                <span>Imię i nazwisko</span>
                <input value={name} onChange={(e) => setName(e.target.value)} required />
                {errs.reporter_name && <em className="err">{errs.reporter_name}</em>}
              </label>
              <label className="field">
                <span>E-mail (na odpowiedź)</span>
                <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
                {errs.reporter_email && <em className="err">{errs.reporter_email}</em>}
              </label>
            </div>
          )}
          <label className="check">
            <input type="checkbox" checked={goodFaith} onChange={(e) => setGoodFaith(e.target.checked)} />
            <span>Oświadczam w dobrej wierze, że informacje w zgłoszeniu są prawdziwe i kompletne.</span>
          </label>
          {errs.good_faith && <em className="err">{errs.good_faith}</em>}
          {!Object.keys(errs).length && <ErrorBox error={error} />}
          <button className="btn btn-danger" disabled={busy}>Wyślij zgłoszenie</button>
        </form>
      )}
    </Modal>
  )
}
