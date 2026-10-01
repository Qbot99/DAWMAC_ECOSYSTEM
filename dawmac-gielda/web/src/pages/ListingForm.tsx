import { useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { api } from '../api'
import { useSession } from '../auth'
import type { Image, Listing, ListingType } from '../types'
import CarSelect from '../components/CarSelect'
import { ErrorBox, Loading } from '../components/ui'
import { fieldErrors } from '../hooks'
import { shrinkPhoto } from '../photos'

type Values = Record<string, string | boolean>

const EMPTY: Values = {
  title: '', description: '', car_brand_id: '', car_model_id: '', car_year_from: '', car_year_to: '',
  wheel_brand: '', wheel_model: '', diameter: '', width: '', width_rear: '', pcd: '', et: '', et_rear: '',
  center_bore: '', quantity: '4', condition: '', with_tyres: false, tyre_info: '', price: '',
  price_negotiable: false, city: '', voivodeship: '', show_phone: false,
}

function fromListing(l: Listing): Values {
  const s = (v: unknown) => (v === null || v === undefined ? '' : String(v))
  return {
    title: l.title, description: l.description ?? '', car_brand_id: s(l.car.brand_id), car_model_id: s(l.car.model_id),
    car_year_from: s(l.car.year_from), car_year_to: s(l.car.year_to), wheel_brand: s(l.wheel.brand), wheel_model: s(l.wheel.model),
    diameter: s(l.wheel.diameter), width: s(l.wheel.width), width_rear: s(l.wheel.width_rear), pcd: s(l.wheel.pcd),
    et: s(l.wheel.et), et_rear: s(l.wheel.et_rear), center_bore: s(l.wheel.center_bore), quantity: s(l.wheel.quantity),
    condition: s(l.wheel.condition), with_tyres: l.wheel.with_tyres, tyre_info: s(l.wheel.tyre_info), price: s(l.price),
    price_negotiable: l.price_negotiable, city: l.city, voivodeship: s(l.voivodeship), show_phone: !!l.show_phone,
  }
}

export default function ListingForm() {
  const { id } = useParams()
  const [search] = useSearchParams()
  const nav = useNavigate()
  const { user, config } = useSession()
  const editing = !!id

  const [type, setType] = useState<ListingType | ''>((search.get('typ') as ListingType) || '')
  const [v, setV] = useState<Values>({ ...EMPTY, city: user?.city ?? '' })
  const [files, setFiles] = useState<File[]>([])
  const [images, setImages] = useState<Image[]>([])
  const [photoRights, setPhotoRights] = useState(false)
  const [error, setError] = useState<unknown>(null)
  const [busy, setBusy] = useState(false)
  const [loaded, setLoaded] = useState(!editing)
  const errs = fieldErrors(error)
  const max = config?.max_images ?? 8

  useEffect(() => {
    if (!id) return
    api<{ listing: Listing }>(`/listings/${id}`)
      .then(({ listing }) => {
        setType(listing.type)
        setV(fromListing(listing))
        setImages(listing.images)
        setLoaded(true)
      })
      .catch(setError)
  }, [id])

  const previews = useMemo(() => files.map((f) => URL.createObjectURL(f)), [files])
  useEffect(() => () => previews.forEach(URL.revokeObjectURL), [previews])

  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
    setV((p) => ({ ...p, [k]: e.target.type === 'checkbox' ? (e.target as HTMLInputElement).checked : e.target.value }))

  async function pick(e: React.ChangeEvent<HTMLInputElement>) {
    const chosen = Array.from(e.target.files ?? [])
    e.target.value = ''
    const room = max - images.length - files.length
    const shrunk = await Promise.all(chosen.slice(0, room).map(shrinkPhoto))
    if (editing) {
      if (!photoRights) {
        setError(new Error('Najpierw potwierdź, że masz prawa do zdjęć (pole pod zdjęciami).'))
        return
      }
      const fd = new FormData()
      shrunk.forEach((f) => fd.append('images[]', f))
      fd.append('photo_rights', '1')
      setBusy(true)
      try {
        setImages((await api<{ images: Image[] }>(`/listings/${id}/images`, { form: fd })).images)
        setError(null)
      } catch (err) {
        setError(err)
      } finally {
        setBusy(false)
      }
    } else {
      setFiles((f) => [...f, ...shrunk])
    }
  }

  async function deleteImage(imgId: number) {
    try {
      setImages((await api<{ images: Image[] }>(`/listings/${id}/images/${imgId}`, { method: 'DELETE' })).images)
    } catch (err) {
      setError(err)
    }
  }

  async function makeCover(index: number) {
    if (editing) {
      const order = [images[index], ...images.filter((_, i) => i !== index)].map((i) => i.id)
      setImages((await api<{ images: Image[] }>(`/listings/${id}/images/order`, { json: { order } })).images)
    } else {
      setFiles((f) => [f[index], ...f.filter((_, i) => i !== index)])
    }
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    if (!type) return
    const fd = new FormData()
    fd.append('type', type)
    for (const [k, val] of Object.entries(v)) {
      if (typeof val === 'boolean') {
        if (val) fd.append(k, '1')
      } else fd.append(k, val)
    }
    if (photoRights) fd.append('photo_rights', '1')
    if (!editing) files.forEach((f) => fd.append('images[]', f))

    setBusy(true)
    setError(null)
    try {
      const r = await api<{ listing: Listing }>(editing ? `/listings/${id}` : '/listings', { form: fd })
      nav(`/ogloszenie/${r.listing.id}`)
    } catch (err) {
      setError(err)
      setBusy(false)
      window.scrollTo({ top: 0, behavior: 'smooth' })
    }
  }

  if (!loaded) return error ? <div className="container"><ErrorBox error={error} /></div> : <Loading />

  if (!type) {
    return (
      <div className="container narrow">
        <h1>Dodaj ogłoszenie</h1>
        <div className="type-choice">
          <button className="card type-card" onClick={() => setType('sell')}>
            <b>Sprzedam</b>
            <span>Mam felgi do sprzedania. Dodam zdjęcia i parametry.</span>
          </button>
          <button className="card type-card" onClick={() => setType('buy')}>
            <b>Kupię</b>
            <span>Szukam felg do swojego auta. Napiszę, czego potrzebuję.</span>
          </button>
        </div>
      </div>
    )
  }

  const sell = type === 'sell'
  const field = (k: string, label: string, props: React.InputHTMLAttributes<HTMLInputElement> = {}) => (
    <label className="field">
      <span>{label}</span>
      <input value={String(v[k])} onChange={set(k)} {...props} />
      {errs[k] && <em className="err">{errs[k]}</em>}
    </label>
  )

  return (
    <div className="container narrow">
      <h1>{editing ? 'Edytuj ogłoszenie' : sell ? 'Sprzedam felgi' : 'Kupię felgi'}</h1>
      {!editing && (
        <p className="muted">
          {sell ? 'Szukasz felg? ' : 'Sprzedajesz felgi? '}
          <button className="link-btn" onClick={() => setType(sell ? 'buy' : 'sell')}>Zmień na „{sell ? 'Kupię' : 'Sprzedam'}”</button>
        </p>
      )}
      {Object.keys(errs).length === 0 && <ErrorBox error={error} />}
      {Object.keys(errs).length > 0 && <div className="notice notice-error">Popraw zaznaczone pola.</div>}

      <form onSubmit={submit} className="form">
        <section className="card pad">
          <h2>Zdjęcia {sell ? '' : <small className="muted">(opcjonalnie)</small>}</h2>
          <div className="photos">
            {images.map((im, i) => (
              <div key={im.id} className="photo">
                <img src={im.thumb} alt="" />
                {i === 0 ? <span className="cover">Okładka</span> : <button type="button" className="cover-btn" onClick={() => makeCover(i)}>Okładka</button>}
                <button type="button" className="x" onClick={() => deleteImage(im.id)} aria-label="Usuń zdjęcie">✕</button>
              </div>
            ))}
            {previews.map((src, i) => (
              <div key={src} className="photo">
                <img src={src} alt="" />
                {i === 0 ? <span className="cover">Okładka</span> : <button type="button" className="cover-btn" onClick={() => makeCover(i)}>Okładka</button>}
                <button type="button" className="x" onClick={() => setFiles((f) => f.filter((_, j) => j !== i))} aria-label="Usuń zdjęcie">✕</button>
              </div>
            ))}
            {images.length + files.length < max && (
              <label className="photo photo-add">
                <input type="file" accept="image/jpeg,image/png,image/webp" multiple onChange={pick} disabled={busy} />
                <span>＋<br />Dodaj zdjęcia</span>
              </label>
            )}
          </div>
          <p className="muted small">Do {max} zdjęć. Usuwamy z nich dane lokalizacji (EXIF). Zasłoń tablice rejestracyjne i twarze.</p>
          {errs.images && <em className="err">{errs.images}</em>}
          <label className="check">
            <input type="checkbox" checked={photoRights} onChange={(e) => setPhotoRights(e.target.checked)} />
            <span>Zdjęcia zrobiłem sam lub mam zgodę autora i udzielam licencji na ich pokazywanie w giełdzie (<Link to="/regulamin#zdjecia" target="_blank">regulamin</Link>).</span>
          </label>
          {errs.photo_rights && <em className="err">{errs.photo_rights}</em>}
        </section>

        <section className="card pad">
          <h2>Ogłoszenie</h2>
          {field('title', 'Tytuł', { required: true, maxLength: 120, placeholder: sell ? 'np. BBS CH-R 19" 5x112 komplet' : 'np. Szukam felg 18" do Audi A4 B8' })}
          <label className="field">
            <span>Opis</span>
            <textarea value={String(v.description)} onChange={set('description')} rows={6} required maxLength={5000}
              placeholder={sell ? 'Stan, wady, naprawy, czy były prostowane/spawane, powód sprzedaży…' : 'Jakie felgi, rozmiar, styl, stan, termin…'} />
            {errs.description && <em className="err">{errs.description}</em>}
          </label>
        </section>

        <section className="card pad">
          <h2>{sell ? 'Do jakiego auta pasują' : 'Do jakiego auta'}</h2>
          <div className="grid2">
            <CarSelect
              brandId={String(v.car_brand_id)}
              modelId={String(v.car_model_id)}
              anyLabel={sell ? 'Nie wiem / różne' : 'Wybierz markę'}
              errors={errs}
              onChange={(b, m) => setV((p) => ({ ...p, car_brand_id: b, car_model_id: m }))}
            />
            {field('car_year_from', 'Rocznik od', { type: 'number', min: 1950, max: 2100, inputMode: 'numeric' })}
            {field('car_year_to', 'Rocznik do', { type: 'number', min: 1950, max: 2100, inputMode: 'numeric' })}
          </div>
        </section>

        <section className="card pad">
          <h2>{sell ? 'Parametry felg' : 'Jakich felg szukam'} {!sell && <small className="muted">(puste = obojętne)</small>}</h2>
          <div className="grid3">
            {field('diameter', 'Średnica (cale)', { inputMode: 'decimal', placeholder: '18', required: sell })}
            {field('width', 'Szerokość (J)', { inputMode: 'decimal', placeholder: '8,5' })}
            {field('pcd', 'Rozstaw (PCD)', { placeholder: '5x112', required: sell })}
            {field('et', 'ET', { inputMode: 'numeric', placeholder: '35', required: sell })}
            {field('center_bore', 'Otwór centralny (mm)', { inputMode: 'decimal', placeholder: '66,6' })}
            {field('quantity', 'Liczba sztuk', { type: 'number', min: 1, max: 8 })}
            {field('width_rear', 'Szerokość tył (jeśli inna)', { inputMode: 'decimal' })}
            {field('et_rear', 'ET tył (jeśli inne)', { inputMode: 'numeric' })}
            {field('wheel_brand', 'Marka felg', { placeholder: 'BBS, OZ, oryginał Audi…' })}
            {field('wheel_model', 'Model felg')}
            <label className="field">
              <span>Stan</span>
              <select value={String(v.condition)} onChange={set('condition')} required={sell}>
                <option value="">{sell ? 'Wybierz…' : 'Obojętny'}</option>
                <option value="new">Nowe</option>
                <option value="used">Używane</option>
                <option value="damaged">Uszkodzone</option>
              </select>
              {errs.condition && <em className="err">{errs.condition}</em>}
            </label>
          </div>
          <label className="check">
            <input type="checkbox" checked={!!v.with_tyres} onChange={set('with_tyres')} />
            <span>{sell ? 'Z oponami' : 'Mogą być z oponami'}</span>
          </label>
          {v.with_tyres && field('tyre_info', 'Opony', { placeholder: 'np. Michelin PS4 225/40 R18, bieżnik 5 mm' })}
        </section>

        <section className="card pad">
          <h2>{sell ? 'Cena i odbiór' : 'Budżet i lokalizacja'}</h2>
          <div className="grid3">
            {field('price', sell ? 'Cena za komplet (zł)' : 'Budżet do (zł)', { type: 'number', min: 0, inputMode: 'numeric' })}
            {field('city', 'Miasto', { required: true })}
            <label className="field">
              <span>Województwo</span>
              <select value={String(v.voivodeship)} onChange={set('voivodeship')}>
                <option value="">—</option>
                {config?.voivodeships.map((x) => <option key={x} value={x}>{x}</option>)}
              </select>
            </label>
          </div>
          <label className="check">
            <input type="checkbox" checked={!!v.price_negotiable} onChange={set('price_negotiable')} />
            <span>Do negocjacji</span>
          </label>
          <label className="check">
            <input type="checkbox" checked={!!v.show_phone} onChange={set('show_phone')} disabled={!user?.phone} />
            <span>
              Pokaż mój numer telefonu po kliknięciu „Pokaż numer”
              {!user?.phone && <> — <Link to="/konto">dodaj numer w koncie</Link></>}
            </span>
          </label>
          <p className="muted small">
            Ogłaszasz jako: <b>{user?.seller_type === 'company' ? `firma (${user.company_name})` : 'osoba prywatna'}</b> — <Link to="/konto">zmień w koncie</Link>.
            Ogłoszenie będzie widoczne {config?.listing_days ?? 60} dni, potem możesz je wznowić.
          </p>
        </section>

        <button className="btn btn-primary btn-big" disabled={busy}>
          {busy ? 'Zapisuję…' : editing ? 'Zapisz zmiany' : 'Opublikuj ogłoszenie'}
        </button>
      </form>
    </div>
  )
}
