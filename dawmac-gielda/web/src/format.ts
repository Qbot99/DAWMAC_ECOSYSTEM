import type { Condition, Listing } from './types'

export const CONDITION_LABEL: Record<Condition, string> = {
  new: 'Nowe',
  used: 'Używane',
  damaged: 'Uszkodzone',
}

export function price(l: Pick<Listing, 'price' | 'price_negotiable' | 'type'>): string {
  if (l.price === null) return l.price_negotiable ? 'Do negocjacji' : l.type === 'buy' ? 'Budżet: do ustalenia' : '—'
  const p = l.price.toLocaleString('pl-PL') + ' zł'
  return l.type === 'buy' ? 'Budżet do ' + p : p
}

export function size(l: Listing): string {
  const w = l.wheel
  const parts: string[] = []
  if (w.width && w.diameter) parts.push(`${fmt(w.width)}${w.width_rear ? '/' + fmt(w.width_rear) : ''}x${fmt(w.diameter)}"`)
  else if (w.diameter) parts.push(`${fmt(w.diameter)}"`)
  if (w.pcd) parts.push(w.pcd)
  if (w.et !== null) parts.push(`ET${w.et}${w.et_rear !== null ? '/' + w.et_rear : ''}`)
  return parts.join(' · ')
}

export function car(l: Listing): string {
  const c = l.car
  if (!c.brand) return ''
  let s = c.brand + (c.model ? ' ' + c.model : '')
  if (c.year_from || c.year_to) s += ` (${c.year_from ?? '…'}–${c.year_to ?? '…'})`
  return s
}

function fmt(n: number): string {
  return Number.isInteger(n) ? String(n) : n.toFixed(1).replace('.', ',')
}

export function date(s: string): string {
  const d = new Date(s.replace(' ', 'T'))
  const today = new Date()
  if (d.toDateString() === today.toDateString()) {
    return 'dziś ' + d.toLocaleTimeString('pl-PL', { hour: '2-digit', minute: '2-digit' })
  }
  return d.toLocaleDateString('pl-PL', { day: 'numeric', month: 'short', year: d.getFullYear() === today.getFullYear() ? undefined : 'numeric' })
}

export const STATUS: Record<string, string> = {
  active: 'Aktywne',
  sold: 'Sprzedane',
  closed: 'Zakończone',
  expired: 'Wygasło',
  removed: 'Usunięte przez moderację',
}
