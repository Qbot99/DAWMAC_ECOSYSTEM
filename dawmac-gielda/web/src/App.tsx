import { BrowserRouter, Route, Routes } from 'react-router-dom'
import { SessionProvider } from './auth'
import Layout from './components/Layout'
import { RequireAuth } from './components/ui'
import Home from './pages/Home'
import ListingPage from './pages/ListingPage'
import ListingForm from './pages/ListingForm'
import MyListings from './pages/MyListings'
import { ConversationPage, Conversations } from './pages/Messages'
import Notifications from './pages/Notifications'
import Account from './pages/Account'
import UserProfile from './pages/UserProfile'
import Panel from './pages/Panel'
import { Forgot, Login, Register, ResetPassword, Verify } from './pages/AuthPages'
import { Contact, Privacy, Safety, Terms } from './pages/Legal'

export default function App() {
  return (
    <SessionProvider>
      <BrowserRouter>
        <Routes>
          <Route element={<Layout />}>
            <Route index element={<Home />} />
            <Route path="ogloszenie/:id" element={<ListingPage />} />
            <Route path="uzytkownik/:id" element={<UserProfile />} />
            <Route path="dodaj" element={<RequireAuth><ListingForm /></RequireAuth>} />
            <Route path="edytuj/:id" element={<RequireAuth><ListingForm /></RequireAuth>} />
            <Route path="moje" element={<RequireAuth><MyListings /></RequireAuth>} />
            <Route path="wiadomosci" element={<RequireAuth><Conversations /></RequireAuth>} />
            <Route path="wiadomosci/:id" element={<RequireAuth><ConversationPage /></RequireAuth>} />
            <Route path="powiadomienia" element={<RequireAuth><Notifications /></RequireAuth>} />
            <Route path="konto" element={<RequireAuth><Account /></RequireAuth>} />
            <Route path="panel" element={<RequireAuth staff><Panel /></RequireAuth>} />
            <Route path="logowanie" element={<Login />} />
            <Route path="rejestracja" element={<Register />} />
            <Route path="potwierdz" element={<Verify />} />
            <Route path="nie-pamietam-hasla" element={<Forgot />} />
            <Route path="nowe-haslo" element={<ResetPassword />} />
            <Route path="regulamin" element={<Terms />} />
            <Route path="prywatnosc" element={<Privacy />} />
            <Route path="bezpieczenstwo" element={<Safety />} />
            <Route path="kontakt" element={<Contact />} />
            <Route path="*" element={<div className="container"><h1>Nie ma takiej strony</h1></div>} />
          </Route>
        </Routes>
      </BrowserRouter>
    </SessionProvider>
  )
}
