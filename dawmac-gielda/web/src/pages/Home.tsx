import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api, qs } from '../api'
import type { Listing, Paged } from '../types'
import ListingCard from '../components/ListingCard'
import CarSelect from '../components/CarSelect'
import { Empty, ErrorBox, Loading } from '../components/ui'
import { useLoad } from '../hooks'
import { useSession } from '../auth'

const DIAMETERS = [14, 15, 16, 17, 18, 19, 20, 21, 22]
const FILTER_KEYS = ['type', 'q', 'brand_id', 'model_id', 'diameter', 'pcd', 'voivodeship', 'price_max', 'condition', 'seller_type', 'with_tyres', 'sort', 'page']

export default function Home() {
  const [params, setParams] = useSearchParams()
  const { config } = useSession()
  const [showFilters, setShowFilters] = useState(false)
  const get = (k: string) => params.get(k) ?? ''
  const query = Object.fromEntries(FILTER_KEYS.map((k) => [k, get(k)]))
  const key = qs(query)

  const { data, error, loading } = useLoad(() => api<Paged<Listing>>('/listings' + key), [key])

  function set(changes: Record<string, string>) {
    const next = new URLSearchParams(params)
    for (const [k, v] of Object.entries(changes)) {
      if (v) next.set(k, v)
      else next.delete(k)
    }
    if (!('page' in changes)) next.delete('page')
    setParams(next)
  }

  const [q, setQ] = useState(get('q'))
  const type = get('type')
  const activeFilters = FILTER_KEYS.filter((k) => !['type', 'q', 'sort', 'page'].includes(k) && get(k)).length

  return (
    <div className="container">
      <section className="hero">
        <h1>Felgi od ludzi, dla ludzi</h1>
        <p>Sprzedaj swoje felgi albo napisz, do jakiego auta szukasz. Bez opłat i prowizji.</p>
        <form
          className="search"
          onSubmit={(e) => {
            e.preventDefault()
            set({ q })
          }}
        >
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="np. BBS 18, 5x112, Audi A4…" aria-label="Szukaj" />
          <button className="btn btn-primary">Szukaj</button>
        </form>
        <div className="hero-cta">
          <Link to="/dodaj?typ=sell" className="btn btn-light">Sprzedaję felgi</Link>
          <Link to="/dodaj?typ=buy" className="btn btn-light">Szukam felg do auta</Link>
        </div>
      </section>

      <div className="tabs">
        {[['', 'Wszystkie'], ['sell', 'Sprzedam'], ['buy', 'Kupię']].map(([v, label]) => (
          <button key={v} className={type === v ? 'tab active' : 'tab'} onClick={() => set({ type: v })}>
            {label}
          </button>
        ))}
        <button className="tab tab-filters" onClick={() => setShowFilters((s) => !s)}>
          Filtry{activeFilters > 0 && <span className="badge">{activeFilters}</span>}
        </button>
      </div>

      <div className={'filters card' + (showFilters ? ' open' : '')}>
        <CarSelect brandId={get('brand_id')} modelId={get('model_id')} onChange={(b, m) => set({ brand_id: b, model_id: m })} />
        <label className="field">
          <span>Średnica</span>
          <select value={get('diameter')} onChange={(e) => set({ diameter: e.target.value })}>
            <option value="">Dowolna</option>
            {DIAMETERS.map((d) => <option key={d} value={d}>{d}"</option>)}
          </select>
        </label>
        <label className="field">
          <span>Rozstaw</span>
          <input
            defaultValue={get('pcd')}
            placeholder="5x112"
            onBlur={(e) => e.target.value !== get('pcd') && set({ pcd: e.target.value })}
            onKeyDown={(e) => e.key === 'Enter' && set({ pcd: (e.target as HTMLInputElement).value })}
          />
        </label>
        <label className="field">
          <span>Województwo</span>
          <select value={get('voivodeship')} onChange={(e) => set({ voivodeship: e.target.value })}>
            <option value="">Cała Polska</option>
            {config?.voivodeships.map((v) => <option key={v} value={v}>{v}</option>)}
          </select>
        </label>
        <label className="field">
          <span>Stan</span>
          <select value={get('condition')} onChange={(e) => set({ condition: e.target.value })}>
            <option value="">Dowolny</option>
            <option value="new">Nowe</option>
            <option value="used">Używane</option>
            <option value="damaged">Uszkodzone</option>
          </select>
        </label>
        <label className="field">
          <span>Cena do (zł)</span>
          <input
            type="number"
            min={0}
            defaultValue={get('price_max')}
            onBlur={(e) => e.target.value !== get('price_max') && set({ price_max: e.target.value })}
          />
        </label>
        <label className="field">
          <span>Ogłoszeniodawca</span>
          <select value={get('seller_type')} onChange={(e) => set({ seller_type: e.target.value })}>
            <option value="">Wszyscy</option>
            <option value="private">Osoby prywatne</option>
            <option value="company">Firmy</option>
          </select>
        </label>
        <label className="check">
          <input type="checkbox" checked={get('with_tyres') === '1'} onChange={(e) => set({ with_tyres: e.target.checked ? '1' : '' })} />
          <span>Z oponami</span>
        </label>
        {activeFilters > 0 && (
          <button className="btn btn-ghost" onClick={() => setParams(type ? { type } : {})}>Wyczyść filtry</button>
        )}
      </div>

      <div className="list-head">
        <span className="muted">{data ? `${data.total} ogłoszeń` : ' '}</span>
        <select value={get('sort')} onChange={(e) => set({ sort: e.target.value })} aria-label="Sortowanie">
          <option value="">Najnowsze</option>
          <option value="price_asc">Najtańsze</option>
          <option value="price_desc">Najdroższe</option>
        </select>
      </div>

      <ErrorBox error={error} />
      {loading && !data ? (
        <Loading />
      ) : data && data.items.length === 0 ? (
        <Empty>
          Nic nie znaleźliśmy.{' '}
          {type !== 'sell' ? <Link to="/dodaj?typ=buy">Dodaj ogłoszenie „Kupię”</Link> : <Link to="/dodaj?typ=sell">Sprzedaj swoje felgi</Link>}{' '}
          — ktoś może mieć to, czego szukasz.
        </Empty>
      ) : (
        <div className={'grid' + (loading ? ' dim' : '')}>
          {data?.items.map((l) => <ListingCard key={l.id} l={l} />)}
        </div>
      )}

      {data && data.pages > 1 && (
        <div className="pager">
          <button className="btn btn-ghost" disabled={data.page <= 1} onClick={() => set({ page: String(data.page - 1) })}>← Poprzednia</button>
          <span>{data.page} / {data.pages}</span>
          <button className="btn btn-ghost" disabled={data.page >= data.pages} onClick={() => set({ page: String(data.page + 1) })}>Następna →</button>
        </div>
      )}

      <p className="muted small ranking-note">
        Kolejność: domyślnie od najnowszych. Nie promujemy żadnych ogłoszeń za opłatą. <Link to="/regulamin#kolejnosc">Zasady</Link>
      </p>
    </div>
  )
}
