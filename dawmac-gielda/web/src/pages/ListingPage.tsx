import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api } from '../api'
import { useSession } from '../auth'
import type { Listing } from '../types'
import { CONDITION_LABEL, car, date, price, size, STATUS } from '../format'
import { ErrorBox, Loading } from '../components/ui'
import { useLoad } from '../hooks'
import ReportDialog from '../components/ReportDialog'
import DecisionDialog, { type Decision } from '../components/DecisionDialog'

export default function ListingPage() {
  const { id } = useParams()
  const { user } = useSession()
  const nav = useNavigate()
  const { data, error, loading, setData } = useLoad(() => api<{ listing: Listing }>(`/listings/${id}`), [id])
  const [photo, setPhoto] = useState(0)
  const [report, setReport] = useState(false)
  const [phone, setPhone] = useState<string | null>(null)
  const [actionError, setActionError] = useState<unknown>(null)
  const [decision, setDecision] = useState<Decision | null>(null)
  const reload = async () => setData(await api<{ listing: Listing }>(`/listings/${id}`))

  if (loading) return <Loading />
  if (error || !data) return <div className="container"><ErrorBox error={error} /><Link to="/">← Wróć do ogłoszeń</Link></div>

  const l = data.listing
  const img = l.images[photo] ?? l.images[0]
  const w = l.wheel

  async function showPhone() {
    if (!user) return nav('/logowanie?next=' + encodeURIComponent(`/ogloszenie/${l.id}`))
    try {
      setPhone((await api<{ phone: string }>(`/listings/${l.id}/phone`)).phone)
    } catch (e) {
      setActionError(e)
    }
  }

  async function toggleFav() {
    if (!user) return nav('/logowanie?next=' + encodeURIComponent(`/ogloszenie/${l.id}`))
    const r = await api<{ is_favorite: boolean }>(`/listings/${l.id}/favorite`, { json: { on: !l.is_favorite } })
    setData({ listing: { ...l, is_favorite: r.is_favorite } })
  }

  async function setStatus(status: string) {
    try {
      const r = await api<{ listing: Listing }>(`/listings/${l.id}/status`, { json: { status } })
      setData(r)
    } catch (e) {
      setActionError(e)
    }
  }

  async function remove() {
    if (!confirm('Usunąć ogłoszenie na stałe? Zdjęcia i rozmowy też znikną.')) return
    await api(`/listings/${l.id}`, { method: 'DELETE' })
    nav('/moje')
  }

  const specs: [string, string | null][] = [
    ['Auto', car(l) || null],
    ['Marka felg', [w.brand, w.model].filter(Boolean).join(' ') || null],
    ['Średnica', w.diameter ? `${w.diameter}"` : null],
    ['Szerokość', w.width ? `${w.width}J${w.width_rear ? ' / tył ' + w.width_rear + 'J' : ''}` : null],
    ['Rozstaw (PCD)', w.pcd],
    ['ET', w.et !== null ? `${w.et}${w.et_rear !== null ? ' / tył ' + w.et_rear : ''}` : null],
    ['Otwór centralny', w.center_bore ? `${w.center_bore} mm` : null],
    ['Sztuk', w.quantity ? String(w.quantity) : null],
    ['Stan', w.condition ? CONDITION_LABEL[w.condition] : null],
    ['Opony', w.with_tyres ? w.tyre_info || 'tak' : null],
  ]

  return (
    <div className="container listing-page">
      <Link to="/" className="back">← Ogłoszenia</Link>

      {l.status !== 'active' && (
        <div className={'notice ' + (l.status === 'removed' ? 'notice-error' : 'notice-warn')}>
          Status: <b>{STATUS[l.status]}</b>
          {l.removal_reason && <> — powód: {l.removal_reason}</>}
        </div>
      )}

      <div className="listing-layout">
        <div className="listing-main">
          {l.images.length > 0 ? (
            <div className="gallery">
              <a href={img.url} target="_blank" rel="noreferrer" className="gallery-main">
                <img src={img.url} alt={l.title} />
              </a>
              {l.images.length > 1 && (
                <div className="gallery-thumbs">
                  {l.images.map((im, i) => (
                    <button key={im.id} className={i === photo ? 'active' : ''} onClick={() => setPhoto(i)}>
                      <img src={im.thumb} alt="" />
                    </button>
                  ))}
                </div>
              )}
            </div>
          ) : (
            <div className="gallery-empty">{l.type === 'buy' ? 'Ogłoszenie „Kupię” — bez zdjęć' : 'Brak zdjęć'}</div>
          )}

          <div className="card pad">
            <span className={'type-tag inline type-' + l.type}>{l.type === 'sell' ? 'Sprzedam' : 'Kupię'}</span>
            <h1 className="listing-title">{l.title}</h1>
            <div className="listing-size big">{size(l)}</div>
            <div className="price big">{price(l)}{l.price !== null && l.price_negotiable && <small> · do negocjacji</small>}</div>
            <div className="muted small">📍 {l.city}{l.voivodeship && `, ${l.voivodeship}`} · dodane {date(l.created_at)}{l.views !== undefined && ` · ${l.views} wyświetleń`}</div>
          </div>

          <div className="card pad">
            <h2>{l.type === 'buy' ? 'Czego szukam' : 'Parametry'}</h2>
            <dl className="specs">
              {specs.filter(([, v]) => v).map(([k, v]) => (
                <div key={k}><dt>{k}</dt><dd>{v}</dd></div>
              ))}
            </dl>
            <h2>Opis</h2>
            <p className="description">{l.description}</p>
          </div>
        </div>

        <aside className="listing-side">
          <div className="card pad seller">
            <div className="seller-name">
              <Link to={`/uzytkownik/${l.seller.id}`}>{l.seller.display_name}</Link>
              <span className={'chip ' + (l.seller.seller_type === 'company' ? 'chip-company' : '')}>
                {l.seller.seller_type === 'company' ? 'Firma' : 'Osoba prywatna'}
              </span>
            </div>
            {l.seller.company_name && <div className="small">{l.seller.company_name}</div>}
            <div className="muted small">Na giełdzie od {l.seller.since}</div>
            {l.seller.seller_type === 'private' && l.type === 'sell' && (
              <p className="muted small">Kupujesz od osoby prywatnej: nie masz 14 dni na zwrot ani rękojmi konsumenckiej.</p>
            )}

            <ErrorBox error={actionError} />

            {l.is_owner ? (
              <div className="stack">
                {l.status !== 'removed' && <Link to={`/edytuj/${l.id}`} className="btn btn-primary">Edytuj</Link>}
                {l.status === 'active' && (
                  <button className="btn" onClick={() => setStatus('sold')}>{l.type === 'sell' ? 'Sprzedane' : 'Kupione'}</button>
                )}
                {l.status === 'active' && <button className="btn btn-ghost" onClick={() => setStatus('closed')}>Zakończ</button>}
                {['sold', 'closed', 'expired'].includes(l.status) && (
                  <button className="btn" onClick={() => setStatus('active')}>Wznów ogłoszenie</button>
                )}
                <button className="btn btn-ghost danger" onClick={remove}>Usuń</button>
              </div>
            ) : (
              l.status === 'active' && (
                <div className="stack">
                  <ContactForm listing={l} />
                  {l.has_phone && (
                    phone ? <a className="btn" href={`tel:${phone}`}>📞 {phone}</a> : <button className="btn" onClick={showPhone}>📞 Pokaż numer</button>
                  )}
                  <button className="btn btn-ghost" onClick={toggleFav}>{l.is_favorite ? '★ Obserwujesz' : '☆ Obserwuj'}</button>
                </div>
              )
            )}
          </div>

          {l.can_moderate && (
            <div className="card pad staff-box">
              <b>Tryb pracownika</b>
              <p className="muted small">Zmiany zapisują się w logu decyzji, a autor dostaje powiadomienie.</p>
              <div className="stack">
                <Link to={`/edytuj/${l.id}`} className="btn btn-primary">Edytuj ogłoszenie</Link>
                {l.status === 'active' && (
                  <button className="btn" onClick={() => setStatus('sold')}>Oznacz jako {l.type === 'sell' ? 'sprzedane' : 'kupione'}</button>
                )}
                {['sold', 'closed', 'expired'].includes(l.status) && (
                  <button className="btn" onClick={() => setStatus('active')}>Wznów ogłoszenie</button>
                )}
                {l.status === 'removed' ? (
                  <button className="btn" onClick={() => setDecision({ title: 'Przywróć ogłoszenie', hint: 'Dlaczego przywracasz (dostanie to autor)', confirm: 'Przywróć', run: (reason) => api(`/mod/listings/${l.id}/restore`, { json: { reason } }) })}>Przywróć</button>
                ) : (
                  <button className="btn btn-danger" onClick={() => setDecision({ title: 'Usuń ogłoszenie', hint: 'Uzasadnienie dla autora: co narusza regulamin lub prawo', confirm: 'Usuń', run: (reason) => api(`/mod/listings/${l.id}/remove`, { json: { reason } }) })}>Usuń (moderacja)</button>
                )}
                <Link to={`/uzytkownik/${l.seller.id}`} className="btn btn-ghost">Profil autora</Link>
              </div>
            </div>
          )}

          <div className="card pad tips">
            <b>Bezpiecznie kupuj i sprzedawaj</b>
            <ul>
              <li>Najlepiej odbiór osobisty i obejrzenie felg na miejscu.</li>
              <li>Nie płać przedpłat „za kuriera” i nie klikaj w linki do płatności od nieznajomych.</li>
              <li>Sprawdź, czy felgi nie są krzywe, pęknięte ani spawane.</li>
            </ul>
            <Link to="/bezpieczenstwo" className="small">Więcej porad</Link>
          </div>

          {!l.is_owner && !l.can_moderate && (
            <button className="link-btn danger small" onClick={() => setReport(true)}>⚑ Zgłoś ogłoszenie</button>
          )}
        </aside>
      </div>

      {decision && <DecisionDialog d={decision} onClose={() => setDecision(null)} onDone={() => { setDecision(null); reload().catch(setActionError) }} />}
      {report && <ReportDialog listingId={l.id} onClose={() => setReport(false)} />}
    </div>
  )
}

function ContactForm({ listing }: { listing: Listing }) {
  const { user } = useSession()
  const nav = useNavigate()
  const [body, setBody] = useState('')
  const [error, setError] = useState<unknown>(null)
  const [busy, setBusy] = useState(false)

  if (!user) {
    return (
      <Link className="btn btn-primary" to={'/logowanie?next=' + encodeURIComponent(`/ogloszenie/${listing.id}`)}>
        Zaloguj się, żeby napisać
      </Link>
    )
  }

  async function send(e: React.FormEvent) {
    e.preventDefault()
    setBusy(true)
    try {
      const r = await api<{ conversation_id: number }>(`/listings/${listing.id}/messages`, { json: { body } })
      nav(`/wiadomosci/${r.conversation_id}`)
    } catch (err) {
      setError(err)
      setBusy(false)
    }
  }

  return (
    <form onSubmit={send} className="stack">
      <textarea
        value={body}
        onChange={(e) => setBody(e.target.value)}
        rows={3}
        placeholder={listing.type === 'sell' ? 'Dzień dobry, czy felgi są aktualne?' : 'Dzień dobry, mam felgi, których Pan szuka…'}
        required
      />
      <ErrorBox error={error} />
      <button className="btn btn-primary" disabled={busy || !body.trim()}>Napisz wiadomość</button>
    </form>
  )
}
