import { useState } from 'react'
import { ErrorBox, Modal } from './ui'

/** Decyzja pracownika z uzasadnieniem — dostaje je użytkownik (DSA art. 17). */
export interface Decision {
  title: string
  hint: string
  confirm: string
  run: (reason: string) => Promise<unknown>
}

export default function DecisionDialog({ d, onClose, onDone }: { d: Decision; onClose: () => void; onDone: () => void }) {
  const [reason, setReason] = useState('')
  const [error, setError] = useState<unknown>(null)
  const [busy, setBusy] = useState(false)
  return (
    <Modal title={d.title} onClose={onClose}>
      <form
        className="form"
        onSubmit={async (e) => {
          e.preventDefault()
          setBusy(true)
          try {
            await d.run(reason)
            onDone()
          } catch (err) {
            setError(err)
            setBusy(false)
          }
        }}
      >
        <label className="field">
          <span>{d.hint}</span>
          <textarea rows={4} value={reason} onChange={(e) => setReason(e.target.value)} required minLength={10}
            placeholder="np. Ogłoszenie zawiera podróbki z logo BBS (pkt 5 regulaminu — zakazane treści)." />
        </label>
        <ErrorBox error={error} />
        <button className="btn btn-danger" disabled={busy}>{d.confirm}</button>
      </form>
    </Modal>
  )
}
