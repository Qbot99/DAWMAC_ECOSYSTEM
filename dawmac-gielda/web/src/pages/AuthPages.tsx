import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { api } from '../api'
import { useSession } from '../auth'
import type { User } from '../types'
import { ErrorBox } from '../components/ui'
import { fieldErrors } from '../hooks'

function useAfterLogin() {
  const [params] = useSearchParams()
  const nav = useNavigate()
  const { refresh } = useSession()
  const next = params.get('next')
  return async () => {
    await refresh()
    nav(next && next.startsWith('/') ? next : '/', { replace: true })
  }
}

export function Login() {
  const done = useAfterLogin()
  const [params] = useSearchParams()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<unknown>(null)
  const [busy, setBusy] = useState(false)

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setBusy(true)
    try {
      await api('/auth/login', { json: { email, password } })
      await done()
    } catch (err) {
      setError(err)
      setBusy(false)
    }
  }

  return (
    <div className="container narrow auth">
      <h1>Zaloguj się</h1>
      <form onSubmit={submit} className="form card pad">
        <label className="field"><span>E-mail</span><input type="email" autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} required /></label>
        <label className="field"><span>Hasło</span><input type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} required /></label>
        <ErrorBox error={error} />
        <button className="btn btn-primary" disabled={busy}>Zaloguj</button>
        <div className="row spread small">
          <Link to="/nie-pamietam-hasla">Nie pamiętam hasła</Link>
          <Link to={'/rejestracja' + (params.get('next') ? '?next=' + encodeURIComponent(params.get('next')!) : '')}>Załóż konto</Link>
        </div>
      </form>
    </div>
  )
}

export function Register() {
  const done = useAfterLogin()
  const [v, setV] = useState({
    email: '', password: '', display_name: '', city: '', phone: '', seller_type: 'private', company_name: '', company_nip: '',
    accept_terms: false, adult: false, marketing_consent: false,
  })
  const [error, setError] = useState<unknown>(null)
  const [busy, setBusy] = useState(false)
  const errs = fieldErrors(error)
  const set = (k: keyof typeof v) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setV((p) => ({ ...p, [k]: e.target.type === 'checkbox' ? (e.target as HTMLInputElement).checked : e.target.value }))

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setBusy(true)
    try {
      await api('/auth/register', { json: v })
      await done()
    } catch (err) {
      setError(err)
      setBusy(false)
    }
  }

  const input = (k: keyof typeof v, label: string, props: React.InputHTMLAttributes<HTMLInputElement> = {}) => (
    <label className="field">
      <span>{label}</span>
      <input value={String(v[k])} onChange={set(k)} {...props} />
      {errs[k] && <em className="err">{errs[k]}</em>}
    </label>
  )

  return (
    <div className="container narrow auth">
      <h1>Załóż konto</h1>
      <p className="muted">Konto jest bezpłatne. Potrzebujemy tylko e-maila i nazwy, którą zobaczą inni.</p>
      <form onSubmit={submit} className="form card pad">
        {input('email', 'E-mail', { type: 'email', autoComplete: 'email', required: true })}
        {input('password', 'Hasło (min. 8 znaków)', { type: 'password', autoComplete: 'new-password', required: true, minLength: 8 })}
        {input('display_name', 'Nazwa wyświetlana', { required: true, maxLength: 60, placeholder: 'np. Tomek z Krakowa' })}
        <div className="grid2">
          {input('city', 'Miasto (opcjonalnie)')}
          {input('phone', 'Telefon (opcjonalnie, ukryty)', { type: 'tel', autoComplete: 'tel' })}
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
            {input('company_name', 'Nazwa firmy', { required: true })}
            {input('company_nip', 'NIP', { required: true })}
          </div>
        )}
        <label className="check">
          <input type="checkbox" checked={v.accept_terms} onChange={set('accept_terms')} />
          <span>Akceptuję <Link to="/regulamin" target="_blank">regulamin</Link> i zapoznałem się z <Link to="/prywatnosc" target="_blank">polityką prywatności</Link>. <b>(wymagane)</b></span>
        </label>
        {errs.accept_terms && <em className="err">{errs.accept_terms}</em>}
        <label className="check">
          <input type="checkbox" checked={v.adult} onChange={set('adult')} />
          <span>Mam ukończone 18 lat. <b>(wymagane)</b></span>
        </label>
        {errs.adult && <em className="err">{errs.adult}</em>}
        <label className="check">
          <input type="checkbox" checked={v.marketing_consent} onChange={set('marketing_consent')} />
          <span>
            Chcę dostawać od DAWMAC e-maile i powiadomienia z propozycjami felg i realizacji. (opcjonalne — możesz to wyłączyć w każdej chwili w ustawieniach konta)
          </span>
        </label>
        {Object.keys(errs).length === 0 && <ErrorBox error={error} />}
        <button className="btn btn-primary" disabled={busy}>Załóż konto</button>
        <div className="small">Masz konto? <Link to="/logowanie">Zaloguj się</Link></div>
      </form>
    </div>
  )
}

export function Verify() {
  const [params] = useSearchParams()
  const { refresh } = useSession()
  const [state, setState] = useState<'wait' | 'ok' | 'err'>('wait')
  const [error, setError] = useState<unknown>(null)
  const once = useRef(false)

  useEffect(() => {
    if (once.current) return
    once.current = true
    api('/auth/verify', { json: { token: params.get('token') ?? '' } })
      .then(() => (setState('ok'), refresh()))
      .catch((e) => (setState('err'), setError(e)))
  }, [params, refresh])

  return (
    <div className="container narrow auth">
      <h1>Potwierdzenie e-maila</h1>
      {state === 'wait' && <p>Sprawdzam link…</p>}
      {state === 'ok' && (
        <div className="card pad">
          <p>Adres potwierdzony. Możesz dodawać ogłoszenia i pisać wiadomości.</p>
          <Link className="btn btn-primary" to="/dodaj">Dodaj ogłoszenie</Link>
        </div>
      )}
      {state === 'err' && (
        <>
          <ErrorBox error={error} />
          <p>Zaloguj się i wyślij link ponownie z ustawień konta.</p>
        </>
      )}
    </div>
  )
}

export function Forgot() {
  const [email, setEmail] = useState('')
  const [sent, setSent] = useState(false)
  const [error, setError] = useState<unknown>(null)

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    try {
      await api('/auth/forgot', { json: { email } })
      setSent(true)
    } catch (err) {
      setError(err)
    }
  }

  return (
    <div className="container narrow auth">
      <h1>Nie pamiętam hasła</h1>
      {sent ? (
        <div className="card pad">Jeśli konto z adresem <b>{email}</b> istnieje, wysłaliśmy link do ustawienia nowego hasła. Sprawdź też spam.</div>
      ) : (
        <form onSubmit={submit} className="form card pad">
          <label className="field"><span>E-mail konta</span><input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required /></label>
          <ErrorBox error={error} />
          <button className="btn btn-primary">Wyślij link</button>
        </form>
      )}
    </div>
  )
}

export function ResetPassword() {
  const [params] = useSearchParams()
  const nav = useNavigate()
  const { setUser } = useSession()
  const [password, setPassword] = useState('')
  const [error, setError] = useState<unknown>(null)

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    try {
      const r = await api<{ user: User }>('/auth/reset', { json: { token: params.get('token'), password } })
      setUser(r.user)
      nav('/', { replace: true })
    } catch (err) {
      setError(err)
    }
  }

  return (
    <div className="container narrow auth">
      <h1>Nowe hasło</h1>
      <form onSubmit={submit} className="form card pad">
        <label className="field"><span>Nowe hasło (min. 8 znaków)</span><input type="password" autoComplete="new-password" minLength={8} value={password} onChange={(e) => setPassword(e.target.value)} required /></label>
        <ErrorBox error={error} />
        <button className="btn btn-primary">Ustaw hasło</button>
      </form>
    </div>
  )
}
