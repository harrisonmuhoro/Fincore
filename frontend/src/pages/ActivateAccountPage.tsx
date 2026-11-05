import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { Button } from '../components/ui/Button'
import { Input } from '../components/ui/Input'
import { apiPost } from '../lib/api'

export function ActivateAccountPage() {
  const [params] = useSearchParams()
  const [email, setEmail] = useState(params.get('email') ?? '')
  const [token, setToken] = useState(params.get('token') ?? '')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true)
    setMessage('')
    setError('')
    try {
      const response = await apiPost('/auth/activate', { email, token, password, password_confirmation: passwordConfirmation })
      setMessage(response.message)
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Account activation failed.')
    } finally {
      setBusy(false)
    }
  }

  return <section className="mx-auto max-w-md rounded-xl border border-[var(--color-border)] bg-white p-6 shadow-sm sm:p-8">
    <p className="text-sm font-semibold text-[var(--color-brand-700)]">ACCOUNT INVITATION</p><h1 className="mt-2 text-2xl font-semibold">Activate account</h1>
    <form className="mt-6 space-y-4" onSubmit={submit}>
      <label className="block text-sm font-medium">Email<Input type="email" required value={email} onChange={(event) => setEmail(event.target.value)} /></label>
      <label className="block text-sm font-medium">Invitation token<Input required value={token} onChange={(event) => setToken(event.target.value)} /></label>
      <label className="block text-sm font-medium">New password<Input type="password" minLength={12} autoComplete="new-password" required value={password} onChange={(event) => setPassword(event.target.value)} /></label>
      <label className="block text-sm font-medium">Confirm password<Input type="password" minLength={12} autoComplete="new-password" required value={passwordConfirmation} onChange={(event) => setPasswordConfirmation(event.target.value)} /></label>
      {message ? <p role="status" className="rounded-md bg-emerald-50 p-3 text-sm text-emerald-900">{message} <Link className="font-semibold underline" to="/sign-in">Sign in</Link></p> : null}
      {error ? <p role="alert" className="rounded-md bg-red-50 p-3 text-sm text-red-800">{error}</p> : null}
      <Button disabled={busy}>{busy ? 'Activating…' : 'Set password and activate'}</Button>
    </form>
  </section>
}
