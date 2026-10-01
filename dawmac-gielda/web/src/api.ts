// Jedno miejsce na rozmowę z API giełdy. Front i API stoją na tej samej
// domenie, więc wystarczy ścieżka /api i ciasteczko sesji.

export class ApiError extends Error {
  status: number
  fields: Record<string, string>

  constructor(status: number, message: string, fields: Record<string, string> = {}) {
    super(message)
    this.status = status
    this.fields = fields
  }
}

const BASE = '/api'

export async function api<T>(path: string, options: { method?: string; json?: unknown; form?: FormData } = {}): Promise<T> {
  const method = options.method ?? (options.json !== undefined || options.form ? 'POST' : 'GET')
  const headers: Record<string, string> = {}
  let body: BodyInit | undefined

  if (method !== 'GET') headers['X-Gielda'] = '1'
  if (options.form) {
    body = options.form
  } else if (options.json !== undefined) {
    headers['Content-Type'] = 'application/json'
    body = JSON.stringify(options.json)
  }

  let res: Response
  try {
    res = await fetch(BASE + path, { method, headers, body, credentials: 'same-origin' })
  } catch {
    throw new ApiError(0, 'Brak połączenia z internetem.')
  }

  const data = await res.json().catch(() => null)
  if (!res.ok) {
    throw new ApiError(res.status, data?.error ?? 'Coś poszło nie tak.', data?.fields ?? {})
  }
  return data as T
}

export function qs(params: Record<string, string | number | undefined | null>): string {
  const p = new URLSearchParams()
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== null && v !== '') p.set(k, String(v))
  }
  const s = p.toString()
  return s ? '?' + s : ''
}
