import { useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { Button } from '../components/ui/Button'
import { Input } from '../components/ui/Input'
import { hasAccessToken, apiPost, saveAccessToken } from '../lib/api'

type LoginResult = { access_token: string; token_type: string; mfa_enrollment_required: boolean }

export function SignInPage() {
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [code, setCode] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  if (hasAccessToken()) return <Navigate to="/" replace />

  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true)
    setError('')
    try {
      const result = await apiPost<LoginResult>('/auth/login', { email, password, ...(code ? { code } : {}) })
      saveAccessToken(result.data.access_token)
      navigate(result.data.mfa_enrollment_required ? '/mfa-setup' : '/', { replace: true })
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Sign in failed.')
    } finally {
      setBusy(false)
    }
  }

  return <section className="mx-auto max-w-md rounded-xl border border-[var(--color-border)] bg-white p-6 shadow-sm sm:p-8">
    <p className="text-sm font-semibold text-[var(--color-brand-700)]">SECURE ACCESS</p>
    <h1 className="mt-2 text-2xl font-semibold">Sign in</h1>
    <p className="mt-2 text-sm leading-6 text-[var(--color-muted)]">Use your invited account. Authenticator verification is required before accessing the workspace.</p>
    <form className="mt-6 space-y-4" onSubmit={submit}>
      <label className="block text-sm font-medium">Email<Input autoComplete="username" type="email" required value={email} onChange={(event) => setEmail(event.target.value)} /></label>
      <label className="block text-sm font-medium">Password<Input autoComplete="current-password" type="password" required value={password} onChange={(event) => setPassword(event.target.value)} /></label>
      <label className="block text-sm font-medium">Authenticator code <span className="font-normal text-[var(--color-muted)]">(if enrolled)</span><Input inputMode="numeric" autoComplete="one-time-code" pattern="[0-9]{6}" maxLength={6} value={code} onChange={(event) => setCode(event.target.value)} /></label>
      {error ? <p role="alert" className="rounded-md bg-red-50 p-3 text-sm text-red-800">{error}</p> : null}
      <Button className="w-full" disabled={busy}>{busy ? 'Signing in…' : 'Sign in'}</Button>
    </form>
    <p className="mt-5 text-sm text-[var(--color-muted)]">Received an invitation? <Link className="font-medium text-[var(--color-brand-800)] underline" to="/activate-account">Activate your account</Link></p>
  </section>
}
