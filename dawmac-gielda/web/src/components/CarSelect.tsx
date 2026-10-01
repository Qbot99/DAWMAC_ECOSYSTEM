import { useEffect, useState } from 'react'
import { api } from '../api'
import type { CarOption } from '../types'

let brandsCache: Promise<CarOption[]> | null = null
const modelsCache = new Map<number, Promise<CarOption[]>>()

function loadBrands() {
  brandsCache ??= api<{ items: CarOption[] }>('/cars/brands').then((r) => r.items)
  return brandsCache
}
function loadModels(brand: number) {
  if (!modelsCache.has(brand)) {
    modelsCache.set(brand, api<{ items: CarOption[] }>(`/cars/models?brand_id=${brand}`).then((r) => r.items))
  }
  return modelsCache.get(brand)!
}

/** Marka i model auta ze słownika galerii DAWMAC. */
export default function CarSelect({
  brandId,
  modelId,
  onChange,
  anyLabel = 'Dowolna',
  errors = {},
}: {
  brandId: string
  modelId: string
  onChange: (brandId: string, modelId: string) => void
  anyLabel?: string
  errors?: Record<string, string>
}) {
  const [brands, setBrands] = useState<CarOption[]>([])
  const [models, setModels] = useState<CarOption[]>([])

  useEffect(() => {
    loadBrands().then(setBrands).catch(() => {})
  }, [])

  useEffect(() => {
    if (!brandId) {
      setModels([])
      return
    }
    loadModels(Number(brandId)).then(setModels).catch(() => {})
  }, [brandId])

  return (
    <>
      <label className="field">
        <span>Marka auta</span>
        <select value={brandId} onChange={(e) => onChange(e.target.value, '')}>
          <option value="">{anyLabel}</option>
          {brands.map((b) => (
            <option key={b.id} value={b.id}>{b.name}</option>
          ))}
        </select>
        {errors.car_brand_id && <em className="err">{errors.car_brand_id}</em>}
      </label>
      <label className="field">
        <span>Model</span>
        <select value={modelId} disabled={!brandId} onChange={(e) => onChange(brandId, e.target.value)}>
          <option value="">{brandId ? 'Dowolny' : 'Najpierw marka'}</option>
          {models.map((m) => (
            <option key={m.id} value={m.id}>{m.name}</option>
          ))}
        </select>
        {errors.car_model_id && <em className="err">{errors.car_model_id}</em>}
      </label>
    </>
  )
}
