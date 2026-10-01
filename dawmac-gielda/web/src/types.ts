export type ListingType = 'sell' | 'buy'
export type ListingStatus = 'active' | 'sold' | 'closed' | 'expired' | 'removed'
export type Condition = 'new' | 'used' | 'damaged'
export type SellerType = 'private' | 'company'

export interface User {
  id: number
  email: string
  display_name: string
  phone: string | null
  city: string | null
  seller_type: SellerType
  company_name: string | null
  company_nip: string | null
  role: 'user' | 'moderator' | 'admin'
  status: 'active' | 'banned' | 'deleted'
  email_verified: boolean
  marketing_consent: boolean
  notify_matches: boolean
  notify_messages: boolean
  terms_version: string
  terms_current: boolean
  created_at: string
}

export interface Image {
  id: number
  url: string
  thumb: string
  width: number
  height: number
}

export interface Listing {
  id: number
  type: ListingType
  status: ListingStatus
  title: string
  description?: string
  car: {
    brand_id: number | null
    model_id: number | null
    brand: string | null
    model: string | null
    year_from: number | null
    year_to: number | null
  }
  wheel: {
    brand: string | null
    model: string | null
    diameter: number | null
    width: number | null
    width_rear: number | null
    pcd: string | null
    et: number | null
    et_rear: number | null
    center_bore: number | null
    quantity: number | null
    condition: Condition | null
    with_tyres: boolean
    tyre_info: string | null
  }
  price: number | null
  price_negotiable: boolean
  city: string
  voivodeship: string | null
  images: Image[]
  created_at: string
  expires_at: string
  is_owner: boolean
  /** Pracownik oglądający cudze ogłoszenie — może je poprawić lub usunąć. */
  can_moderate?: boolean
  is_favorite?: boolean
  has_phone?: boolean
  show_phone?: boolean
  views?: number
  conversations?: number
  reports?: number
  removal_reason?: string | null
  seller: {
    id: number
    display_name: string
    seller_type: SellerType
    company_name: string | null
    since: string
    email?: string
  }
}

export interface Paged<T> {
  items: T[]
  total: number
  page: number
  pages: number
}

export interface CarOption {
  id: number
  name: string
}

export interface Conversation {
  id: number
  listing: { id: number; title: string; type: ListingType; status: ListingStatus }
  partner: { id: number; display_name: string; active: boolean }
  i_am_owner: boolean
  last_message_at: string
  unread: boolean
  last_message: string | null
}

export interface Message {
  id: number
  mine: boolean
  body: string
  created_at: string
}

export interface Notification {
  id: number
  type: string
  title: string
  body: string | null
  link: string | null
  read: boolean
  created_at: string
}

export interface AppConfig {
  contact_email: string
  authority_email: string
  operator: string
  terms_version: string
  listing_days: number
  max_images: number
  voivodeships: string[]
  report_reasons: Record<string, string>
}
