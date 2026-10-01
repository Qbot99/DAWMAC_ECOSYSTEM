import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api } from '../api'
import { useSession } from '../auth'
import type { User } from '../types'
import { ErrorBox } from '../components/ui'
import { fieldErrors } from '../hooks'

export default function Account() {
  const { user, setUser, refresh } = useSession()
  const nav = useNavigate()
  const [v, setV] = useState({
    display_name: user!.display_name, city: user!.city ?? '', phone: user!.phone ?? '', seller_type: user!.seller_type,
    company_name: user!.company_name ?? '', company_nip: user!.company_nip ?? '', notify_messages: user!.notify_messages,
    notify_matches: user!.notify_matches, marketing_consent: user!.marketing_consent,
  })
  const [pw, setPw] = useState({ current_password: '', new_password: '' })
  const [error, setError] = useState<unknown>(null)
  const [saved, setSaved] = useState('')
  const [deletePw, setDeletePw] = useState('')
  const errs = fieldErrors(error)
  const set = (k: keyof typeof v) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setV((p) => ({ ...p, [k]: e.target.type === 'checkbox' ? (e.target as HTMLInputElement).checked : e.target.value }))

  async function save(body: object, msg: string) {
    setSaved('')
    try {
      const r = await api<{ user: User }>('/me', { json: body })
      setUser(r.user)
      setError(null)
      setSaved(msg)
    } catch (err) {
      setError(err)
    }
  }

  async function logout() {
    await api('/auth/logout', { json: {} })
    await refresh()
    nav('/')
  }

  async function deleteAccount() {
    if (!confirm('Na pewno usunąć konto? Ogłoszenia, zdjęcia i wiadomości znikną bezpowrotnie.')) return
    try {
      await api('/me/delete', { json: { password: deletePw } })
      await refresh()
      nav('/')
    } catch (err) {
      setError(err)
    }
  }

  const u = user!
  const input = (k: keyof typeof v, label: string, props: React.InputHTMLAttributes<HTMLInputElement> = {}) => (
    <label className="field">
      <span>{label}</span>
      <input value={String(v[k])} onChange={set(k)} {...props} />
      {errs[k] && <em className="err">{errs[k]}</em>}
    </label>
  )

  return (
    <div className="container narrow">
      <div className="row spread">
        <h1>Konto</h1>
        <button className="btn btn-ghost" onClick={logout}>Wyloguj</button>
      </div>
      {saved && <div className="notice notice-ok">{saved}</div>}
      {Object.keys(errs).length === 0 && <ErrorBox error={error} />}

      {!u.email_verified && (
        <div className="card pad">
          <b>Adres {u.email} nie jest potwierdzony.</b>
          <p className="small">Bez tego nie dodasz ogłoszenia ani nie napiszesz wiadomości.</p>
          <button className="btn" onClick={() => api('/auth/resend', { json: {} }).then(() => setSaved('Wysłaliśmy nowy link.')).catch(setError)}>
            Wyślij link ponownie
          </button>
        </div>
      )}

      {!u.terms_current && (
        <div className="card pad">
          <b>Zmienił się regulamin.</b>
          <p className="small"><Link to="/regulamin" target="_blank">Przeczytaj nową wersję</Link> i zaakceptuj, żeby dalej korzystać z giełdy.</p>
          <button className="btn btn-primary" onClick={() => save({ accept_terms: true }, 'Dziękujemy, regulamin zaakceptowany.')}>Akceptuję</button>
        </div>
      )}

      <section className="card pad form">
        <h2>Profil</h2>
        <div className="muted small">E-mail: {u.email}</div>
        {input('display_name', 'Nazwa wyświetlana')}
        <div className="grid2">
          {input('city', 'Miasto')}
          {input('phone', 'Telefon (pokazywany tylko po kliknięciu, jeśli zaznaczysz to w ogłoszeniu)', { type: 'tel' })}
        </div>
        <label className="field">
          <span>Ogłaszam się jako</span>
          <select value={v.seller_type} onChange={set('seller_type')}>
            <option value="private">Osoba prywatna</option>
            <option value="company">Firma</option>
          </select>
        </label>
        {v.seller_type === 'company' && (
          <div className="grid2">
            {input('company_name', 'Nazwa firmy')}
            {input('company_nip', 'NIP')}
          </div>
        )}
        <button className="btn btn-primary" onClick={() => save({
          display_name: v.display_name, city: v.city, phone: v.phone, seller_type: v.seller_type,
          company_name: v.company_name, company_nip: v.company_nip,
        }, 'Zapisano profil.')}>Zapisz profil</button>
      </section>

      <section className="card pad form">
        <h2>Powiadomienia i zgody</h2>
        <label className="check">
          <input type="checkbox" checked={v.notify_messages} onChange={set('notify_messages')} />
          <span>E-mail, gdy ktoś napisze do mnie wiadomość</span>
        </label>
        <label className="check">
          <input type="checkbox" checked={v.notify_matches} onChange={set('notify_matches')} />
          <span>Powiadomienia o pasujących ogłoszeniach (gdy ktoś sprzeda felgi, których szukam, lub szuka moich)</span>
        </label>
        <label className="check">
          <input type="checkbox" checked={v.marketing_consent} onChange={set('marketing_consent')} />
          <span>Propozycje felg i realizacji od DAWMAC (e-mail i powiadomienia) — zgoda marketingowa, dobrowolna</span>
        </label>
        <button className="btn btn-primary" onClick={() => save({
          notify_messages: v.notify_messages, notify_matches: v.notify_matches, marketing_consent: v.marketing_consent,
        }, 'Zapisano ustawienia powiadomień.')}>Zapisz</button>
      </section>

      <section className="card pad form">
        <h2>Zmiana hasła</h2>
        <label className="field"><span>Obecne hasło</span><input type="password" value={pw.current_password} onChange={(e) => setPw({ ...pw, current_password: e.target.value })} />{errs.current_password && <em className="err">{errs.current_password}</em>}</label>
        <label className="field"><span>Nowe hasło</span><input type="password" autoComplete="new-password" value={pw.new_password} onChange={(e) => setPw({ ...pw, new_password: e.target.value })} />{errs.new_password && <em className="err">{errs.new_password}</em>}</label>
        <button className="btn" onClick={() => save(pw, 'Hasło zmienione.').then(() => setPw({ current_password: '', new_password: '' }))}>Zmień hasło</button>
      </section>

      <section className="card pad form">
        <h2>Twoje dane</h2>
        <p className="small">Możesz pobrać wszystkie swoje dane albo usunąć konto razem z ogłoszeniami, zdjęciami i wiadomościami.</p>
        <a className="btn" href="/api/me/export" download>Pobierz moje dane (JSON)</a>
        <label className="field"><span>Hasło, żeby usunąć konto</span><input type="password" value={deletePw} onChange={(e) => setDeletePw(e.target.value)} />{errs.password && <em className="err">{errs.password}</em>}</label>
        <button className="btn btn-danger" disabled={!deletePw} onClick={deleteAccount}>Usuń konto na zawsze</button>
      </section>
    </div>
  )
}
