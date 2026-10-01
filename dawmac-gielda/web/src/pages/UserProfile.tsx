import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { api } from '../api'
import type { Listing } from '../types'
import ListingCard from '../components/ListingCard'
import ReportDialog from '../components/ReportDialog'
import { Empty, ErrorBox, Loading } from '../components/ui'
import { useLoad } from '../hooks'
import { useSession } from '../auth'

interface Profile {
  user: { id: number; display_name: string; city: string | null; seller_type: string; company_name: string | null; since: string }
  listings: Listing[]
}

export default function UserProfile() {
  const { id } = useParams()
  const { user: me } = useSession()
  const { data, error, loading } = useLoad(() => api<Profile>(`/users/${id}`), [id])
  const [report, setReport] = useState(false)

  if (loading) return <Loading />
  if (!data) return <div className="container"><ErrorBox error={error} /></div>
  const u = data.user

  return (
    <div className="container">
      <div className="card pad profile-head">
        <div>
          <h1>{u.display_name}</h1>
          <span className={'chip ' + (u.seller_type === 'company' ? 'chip-company' : '')}>{u.seller_type === 'company' ? 'Firma' : 'Osoba prywatna'}</span>
          {u.company_name && <span className="small"> {u.company_name}</span>}
          <div className="muted small">{u.city && `📍 ${u.city} · `}na giełdzie od {u.since}</div>
        </div>
        {me?.id !== u.id && <button className="link-btn danger small" onClick={() => setReport(true)}>⚑ Zgłoś użytkownika</button>}
      </div>
      <h2>Ogłoszenia ({data.listings.length})</h2>
      {data.listings.length ? (
        <div className="grid">{data.listings.map((l) => <ListingCard key={l.id} l={l} />)}</div>
      ) : (
        <Empty>Brak aktywnych ogłoszeń.</Empty>
      )}
      {report && <ReportDialog userId={u.id} onClose={() => setReport(false)} />}
    </div>
  )
}
