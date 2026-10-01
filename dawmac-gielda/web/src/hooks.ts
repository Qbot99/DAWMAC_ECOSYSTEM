import { useEffect, useState } from 'react'
import { ApiError } from './api'

/** Pobiera dane przy wejściu i przy zmianie klucza. */
export function useLoad<T>(fn: () => Promise<T>, deps: unknown[]): {
  data: T | null
  error: unknown
  loading: boolean
  reload: () => void
  setData: (d: T) => void
} {
  const [data, setData] = useState<T | null>(null)
  const [error, setError] = useState<unknown>(null)
  const [loading, setLoading] = useState(true)
  const [tick, setTick] = useState(0)

  useEffect(() => {
    let alive = true
    setLoading(true)
    fn()
      .then((d) => alive && (setData(d), setError(null)))
      .catch((e) => alive && setError(e))
      .finally(() => alive && setLoading(false))
    return () => {
      alive = false
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [...deps, tick])

  return { data, error, loading, reload: () => setTick((t) => t + 1), setData }
}

export function fieldErrors(e: unknown): Record<string, string> {
  return e instanceof ApiError ? e.fields : {}
}
