import { Link } from 'react-router-dom'
import type { Listing } from '../types'
import { car, price, size, CONDITION_LABEL, STATUS, date } from '../format'

export default function ListingCard({ l, footer }: { l: Listing; footer?: React.ReactNode }) {
  const img = l.images[0]
  return (
    <article className="card listing-card">
      <Link to={`/ogloszenie/${l.id}`} className="listing-card-link">
        <div className={'listing-thumb' + (img ? '' : ' listing-thumb-empty')}>
          {img ? <img src={img.thumb} alt="" loading="lazy" /> : <span>{l.type === 'buy' ? 'Szukam' : 'Brak zdjęcia'}</span>}
          <span className={'type-tag type-' + l.type}>{l.type === 'sell' ? 'Sprzedam' : 'Kupię'}</span>
          {l.status !== 'active' && <span className="status-tag">{STATUS[l.status]}</span>}
        </div>
        <div className="listing-body">
          <h3>{l.title}</h3>
          <div className="listing-size">{size(l) || ' '}</div>
          {car(l) && <div className="muted small">🚗 {car(l)}</div>}
          <div className="listing-meta">
            <b className="price">{price(l)}</b>
            {l.wheel.condition && <span className="chip">{CONDITION_LABEL[l.wheel.condition]}</span>}
            {l.seller.seller_type === 'company' && <span className="chip chip-company">Firma</span>}
          </div>
          <div className="muted small">
            📍 {l.city} · {date(l.created_at)}
          </div>
        </div>
      </Link>
      {footer && <div className="listing-card-footer">{footer}</div>}
    </article>
  )
}
