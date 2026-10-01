import { useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api'
import type { Listing } from '../types'
import ListingCard from '../components/ListingCard'
import { Empty, ErrorBox, Loading } from '../components/ui'
import { useLoad } from '../hooks'

export default function MyListings() {
  const [tab, setTab] = useState<'mine' | 'fav'>('mine')
  const mine = useLoad(() => api<{ items: Listing[] }>(tab === 'mine' ? '/me/listings' : '/me/favorites'), [tab])

  async function setStatus(id: number, status: string) {
    await api(`/listings/${id}/status`, { json: { status } })
    mine.reload()
  }

  return (
    <div className="container">
      <h1>Moje ogłoszenia</h1>
      <div className="tabs">
        <button className={tab === 'mine' ? 'tab active' : 'tab'} onClick={() => setTab('mine')}>Dodane przeze mnie</button>
        <button className={tab === 'fav' ? 'tab active' : 'tab'} onClick={() => setTab('fav')}>Obserwowane</button>
      </div>
      <ErrorBox error={mine.error} />
      {mine.loading ? (
        <Loading />
      ) : !mine.data?.items.length ? (
        <Empty>
          {tab === 'mine' ? <>Nie masz jeszcze ogłoszeń. <Link to="/dodaj">Dodaj pierwsze</Link>.</> : 'Nie obserwujesz żadnych ogłoszeń.'}
        </Empty>
      ) : (
        <div className="grid">
          {mine.data.items.map((l) => (
            <ListingCard
              key={l.id}
              l={l}
              footer={
                tab === 'mine' && (
                  <>
                    <div className="muted small">
                      👁 {l.views ?? 0} · 💬 {l.conversations ?? 0}
                      {l.status === 'active' && <> · do {l.expires_at.slice(0, 10)}</>}
                    </div>
                    {l.status === 'removed' && l.removal_reason && <div className="err small">Powód usunięcia: {l.removal_reason}</div>}
                    <div className="row">
                      {l.status !== 'removed' && <Link className="btn btn-small" to={`/edytuj/${l.id}`}>Edytuj</Link>}
                      {l.status === 'active' && <button className="btn btn-small" onClick={() => setStatus(l.id, 'sold')}>{l.type === 'sell' ? 'Sprzedane' : 'Kupione'}</button>}
                      {['sold', 'closed', 'expired'].includes(l.status) && <button className="btn btn-small" onClick={() => setStatus(l.id, 'active')}>Wznów</button>}
                    </div>
                  </>
                )
              }
            />
          ))}
        </div>
      )}
    </div>
  )
}
