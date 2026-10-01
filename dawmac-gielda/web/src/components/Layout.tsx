import { Link, NavLink, Outlet, useLocation } from 'react-router-dom'
import { useEffect } from 'react'
import { useSession } from '../auth'

export default function Layout() {
  const { user, unreadConversations, unreadNotifications, config } = useSession()
  const { pathname } = useLocation()

  useEffect(() => {
    window.scrollTo(0, 0)
  }, [pathname])

  const staff = user && (user.role === 'moderator' || user.role === 'admin')

  return (
    <div className="app">
      <header className="topbar">
        <div className="topbar-inner">
          <Link to="/" className="brand">
            <img src="/dawmac-logo.png" alt="DAWMAC" width={126} height={28} />
            <span className="brand-badge">Giełda</span>
          </Link>
          <nav className="topnav">
            <NavLink to="/" end>Ogłoszenia</NavLink>
            {user && <NavLink to="/moje">Moje</NavLink>}
            {user && (
              <NavLink to="/wiadomosci">
                Wiadomości{unreadConversations > 0 && <span className="badge">{unreadConversations}</span>}
              </NavLink>
            )}
            {staff && <NavLink to="/panel">Panel</NavLink>}
          </nav>
          <div className="topbar-actions">
            {user ? (
              <>
                <Link to="/powiadomienia" className="icon-btn" aria-label="Powiadomienia">
                  🔔{unreadNotifications > 0 && <span className="badge">{unreadNotifications}</span>}
                </Link>
                <Link to="/konto" className="icon-btn" aria-label="Konto">👤</Link>
              </>
            ) : (
              <Link to="/logowanie" className="btn btn-ghost">Zaloguj</Link>
            )}
            <Link to="/dodaj" className="btn btn-primary">+ Dodaj</Link>
          </div>
        </div>
      </header>
      <div className="hazard" />

      {user && !user.email_verified && (
        <div className="notice notice-warn">
          Potwierdź adres e-mail — link wysłaliśmy na <b>{user.email}</b>. Bez tego nie dodasz ogłoszenia.{' '}
          <Link to="/konto">Wyślij ponownie</Link>
        </div>
      )}
      {user && user.email_verified && !user.terms_current && (
        <div className="notice notice-warn">
          Zmienił się regulamin. <Link to="/konto">Przeczytaj i zaakceptuj</Link>.
        </div>
      )}

      <main className="main">
        <Outlet />
      </main>

      <footer className="footer">
        <div className="footer-inner">
          <div>
            <b>Giełda felg</b> jest bezpłatna. Nie pobieramy opłat ani prowizji i nie jesteśmy stroną transakcji między użytkownikami.
          </div>
          <nav>
            <Link to="/regulamin">Regulamin</Link>
            <Link to="/prywatnosc">Prywatność</Link>
            <Link to="/bezpieczenstwo">Bezpieczne zakupy</Link>
            <Link to="/kontakt">Kontakt</Link>
          </nav>
          <div className="muted small">Serwis prowadzi DAWMAC · {config?.contact_email}</div>
        </div>
      </footer>

      <nav className="bottomnav">
        <NavLink to="/" end>🏠<span>Szukaj</span></NavLink>
        <NavLink to="/moje">📋<span>Moje</span></NavLink>
        <NavLink to="/dodaj" className="bottomnav-add">＋</NavLink>
        <NavLink to="/wiadomosci">
          💬<span>Wiadomości</span>{unreadConversations > 0 && <i className="dot" />}
        </NavLink>
        <NavLink to={user ? '/konto' : '/logowanie'}>👤<span>Konto</span></NavLink>
      </nav>
    </div>
  )
}
