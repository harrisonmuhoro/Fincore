import { useState } from 'react'
import { Navigate, useNavigate } from 'react-router-dom'
import { Button } from '../components/ui/Button'
import { Input } from '../components/ui/Input'
import { apiPost, hasAccessToken } from '../lib/api'

type SetupData = { secret: string; provisioning_uri: string }

export function MfaSetupPage() {
  const navigate = useNavigate()
  const [setup, setSetup] = useState<SetupData>()
  const [code, setCode] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  if (!hasAccessToken()) return <Navigate to="/sign-in" replace />

  async function begin() {
    setBusy(true)
    setError('')
    try {
      const response = await apiPost<SetupData>('/auth/mfa/setup')
      setSetup(response.data)
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Could not start MFA setup.')
    } finally {
      setBusy(false)
    }
  }

  async function confirm(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true)
    setError('')
    try {
      await apiPost('/auth/mfa/confirm', { code })
      navigate('/', { replace: true })
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'MFA confirmation failed.')
    } finally {
      setBusy(false)
    }
  }

  return <section className="mx-auto max-w-xl rounded-xl border border-[var(--color-border)] bg-white p-6 shadow-sm sm:p-8">
    <p className="text-sm font-semibold text-[var(--color-brand-700)]">ACCOUNT SECURITY</p>
    <h1 className="mt-2 text-2xl font-semibold">Set up an authenticator</h1>
    <p className="mt-2 text-sm leading-6 text-[var(--color-muted)]">Your account stays restricted until an authenticator code is confirmed. Store the secret in your authenticator app and keep it private.</p>
    {!setup ? <Button className="mt-6" onClick={() => void begin()} disabled={busy}>{busy ? 'Preparing…' : 'Generate setup details'}</Button> : <>
      <a href={setup.provisioning_uri} className="mt-6 inline-flex rounded-md bg-[var(--color-brand-100)] px-4 py-2 text-sm font-semibold text-[var(--color-brand-950)]">Open authenticator app</a>
      <div className="mt-4 rounded-md bg-slate-50 p-4"><p className="text-xs font-semibold uppercase tracking-wide text-[var(--color-muted)]">Manual setup key</p><code className="mt-2 block break-all text-sm">{setup.secret}</code></div>
      <form className="mt-5 space-y-4" onSubmit={confirm}>
        <label className="block text-sm font-medium">6-digit authenticator code<Input inputMode="numeric" autoComplete="one-time-code" pattern="[0-9]{6}" minLength={6} maxLength={6} required value={code} onChange={(event) => setCode(event.target.value)} /></label>
        {error ? <p role="alert" className="rounded-md bg-red-50 p-3 text-sm text-red-800">{error}</p> : null}
        <Button disabled={busy}>{busy ? 'Checking…' : 'Confirm and continue'}</Button>
      </form>
    </>}
    {error && !setup ? <p role="alert" className="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-800">{error}</p> : null}
  </section>
}
