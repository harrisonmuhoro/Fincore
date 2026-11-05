import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Button } from '../components/ui/Button'
import { Input } from '../components/ui/Input'
import { apiGet, apiPatch, apiPost } from '../lib/api'

type Setting = { key: string; scope_type: string; scope_id: string; value: unknown; type: string; version: number; effective_from: string }
type Page<T> = { data: T[]; current_page: number; last_page: number }

export function SettingsPage() {
  const queryClient = useQueryClient()
  const [drafts, setDrafts] = useState<Record<string, { value: string; reason: string }>>({})
  const [message, setMessage] = useState('')
  const [newError, setNewError] = useState('')
  const settings = useQuery({ queryKey: ['system-settings'], queryFn: ({ signal }) => apiGet<Page<Setting>>('/system/settings', signal) })
  const save = useMutation({
    mutationFn: ({ setting, value, reason }: { setting: Setting; value: unknown; reason: string }) => apiPatch(`/system/settings/${encodeURIComponent(setting.key)}`, {
      scope_type: setting.scope_type, scope_id: setting.scope_id, value, value_type: setting.type, reason, expected_version: setting.version,
    }),
    onSuccess: async () => { setMessage('Setting version saved and audit hash recorded.'); await queryClient.invalidateQueries({ queryKey: ['system-settings'] }) },
  })
  const create = useMutation({ mutationFn: (payload: object) => apiPost('/system/settings', payload), onSuccess: async () => { setMessage('Setting created as version 1.'); await queryClient.invalidateQueries({ queryKey: ['system-settings'] }) } })

  async function createSetting(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setNewError('')
    const form = new FormData(event.currentTarget)
    let value: unknown
    try { value = JSON.parse(String(form.get('value'))) } catch { setNewError('Value must be valid JSON (strings need quotes).'); return }
    try {
      await create.mutateAsync({ key: form.get('key'), scope_type: form.get('scope_type'), scope_id: form.get('scope_id'), value, value_type: form.get('value_type'), reason: form.get('reason'), expected_version: 0 })
      event.currentTarget.reset()
    } catch (cause) { setNewError(cause instanceof Error ? cause.message : 'Setting could not be created.') }
  }

  function submit(setting: Setting) {
    const draft = drafts[setting.key] ?? { value: JSON.stringify(setting.value), reason: '' }
    try {
      const value: unknown = JSON.parse(draft.value)
      setMessage('')
      save.mutate({ setting, value, reason: draft.reason })
    } catch {
      setMessage(`Value for ${setting.key} must be valid JSON (strings need quotes).`)
    }
  }

  const items = settings.data?.data.data ?? []
  return <section className="mx-auto max-w-5xl" aria-labelledby="settings-title">
    <p className="text-sm font-semibold text-[var(--color-brand-700)]">MODULE 01 · SYSTEM FOUNDATION</p><h1 id="settings-title" className="mt-1 text-3xl font-semibold">System settings</h1>
    <p className="mt-3 max-w-3xl text-sm leading-6 text-[var(--color-muted)]">Typed settings are versioned. Changes use expected versions and retain before/after hashes. Secrets must stay in deployment secret configuration.</p>
    {settings.isLoading ? <p className="mt-6 text-sm text-[var(--color-muted)]">Loading settings…</p> : null}
    {settings.isError ? <p role="alert" className="mt-6 rounded-md bg-red-50 p-3 text-sm text-red-800">{settings.error.message}</p> : null}
    {save.isError ? <p role="alert" className="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-800">{save.error.message}</p> : null}
    {message ? <p role="status" className="mt-4 rounded-md bg-slate-100 p-3 text-sm">{message}</p> : null}
    <form className="mt-6 rounded-xl border border-[var(--color-border)] bg-white p-5 shadow-sm" onSubmit={(event) => void createSetting(event)}><h2 className="font-semibold">Create a setting</h2><div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"><label className="text-sm font-medium">Key<Input name="key" required maxLength={120} pattern="[A-Za-z0-9._-]+" /></label><label className="text-sm font-medium">Scope type<Input name="scope_type" required defaultValue="global" /></label><label className="text-sm font-medium">Scope ID<Input name="scope_id" required defaultValue="global" /></label><label className="text-sm font-medium">Type<select name="value_type" className="mt-1 min-h-10 w-full rounded-md border border-[var(--color-border)] bg-white px-3 text-sm"><option>string</option><option>integer</option><option>boolean</option><option>number</option><option>object</option><option>array</option></select></label><label className="text-sm font-medium">Value (JSON)<Input name="value" required placeholder={'"en" or true'} /></label><label className="text-sm font-medium">Reason<Input name="reason" required minLength={8} /></label></div>{newError ? <p role="alert" className="mt-3 text-sm text-red-800">{newError}</p> : null}<Button className="mt-4" disabled={create.isPending}>Create version 1</Button></form>
    {!settings.isLoading && items.length === 0 ? <div className="mt-6 rounded-lg border border-dashed border-[var(--color-border)] bg-white p-6 text-sm text-[var(--color-muted)]">No active settings are configured yet. Values take effect immediately; the platform owner must define high-risk approval classes.</div> : null}
    <div className="mt-6 space-y-4">{items.map((setting) => {
      const draft = drafts[setting.key] ?? { value: JSON.stringify(setting.value), reason: '' }
      return <article key={`${setting.key}:${setting.scope_type}:${setting.scope_id}`} className="rounded-xl border border-[var(--color-border)] bg-white p-5 shadow-sm">
        <div className="flex flex-wrap items-start justify-between gap-2"><div><h2 className="font-semibold">{setting.key}</h2><p className="mt-1 text-xs text-[var(--color-muted)]">{setting.scope_type}:{setting.scope_id} · {setting.type} · v{setting.version}</p></div><time className="text-xs text-[var(--color-muted)]">Effective {new Date(setting.effective_from).toLocaleString()}</time></div>
        <div className="mt-4 grid gap-3 md:grid-cols-[1fr_1fr_auto] md:items-end"><label className="block text-sm font-medium">Value (JSON)<Input value={draft.value} onChange={(event) => setDrafts((old) => ({ ...old, [setting.key]: { ...draft, value: event.target.value } }))} /></label><label className="block text-sm font-medium">Change reason<Input minLength={8} value={draft.reason} onChange={(event) => setDrafts((old) => ({ ...old, [setting.key]: { ...draft, reason: event.target.value } }))} /></label><Button onClick={() => submit(setting)} disabled={save.isPending || draft.reason.trim().length < 8}>Save version</Button></div>
      </article>
    })}</div>
  </section>
}
