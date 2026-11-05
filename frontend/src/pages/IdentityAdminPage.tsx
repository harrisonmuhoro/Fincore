import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import type { FormEvent } from 'react'
import { Button } from '../components/ui/Button'
import { Input } from '../components/ui/Input'
import { apiGet, apiPost } from '../lib/api'

type UserRow = { id: number; name: string; email: string; status: string; mfa_enabled: boolean }
type RoleRow = { id: number; code: string; name: string; status: string; permissions: Array<{ code: string }> }
type RoleAssignment = { id: number; user_id: number; user_name: string; role_code: string; scope_type: string; scope_id: string; workflow_status: string | null }
type Page<T> = { data: T[] }

export function IdentityAdminPage() {
  const client = useQueryClient()
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const users = useQuery({ queryKey: ['iam-users'], queryFn: ({ signal }) => apiGet<Page<UserRow>>('/users', signal) })
  const roles = useQuery({ queryKey: ['iam-roles'], queryFn: ({ signal }) => apiGet<Page<RoleRow>>('/roles', signal) })
  const assignments = useQuery({ queryKey: ['iam-role-assignments'], queryFn: ({ signal }) => apiGet<{ items: RoleAssignment[] }>('/role-assignments', signal) })
  const refresh = async () => { await Promise.all([client.invalidateQueries({ queryKey: ['iam-users'] }), client.invalidateQueries({ queryKey: ['iam-roles'] }), client.invalidateQueries({ queryKey: ['iam-role-assignments'] })]) }
  const invite = useMutation({ mutationFn: (payload: object) => apiPost('/users', payload), onSuccess: async () => { setMessage('Invitation sent using the configured mail transport.'); await refresh() } })
  const createRole = useMutation({ mutationFn: (payload: object) => apiPost('/roles', payload), onSuccess: async () => { setMessage('Role created.'); await refresh() } })
  const assign = useMutation({ mutationFn: ({ id, payload }: { id: number; payload: object }) => apiPost(`/users/${id}/roles`, payload), onSuccess: () => setMessage('Role assignment submitted for independent approval.') })
  const suspend = useMutation({ mutationFn: ({ id, reason }: { id: number; reason: string }) => apiPost(`/users/${id}/suspend`, { reason }), onSuccess: refresh })
  const activateAssignment = useMutation({ mutationFn: (id: number) => apiPost(`/role-assignments/${id}/activate`), onSuccess: refresh })

  async function submit(event: FormEvent<HTMLFormElement>, action: (payload: Record<string, FormDataEntryValue>) => Promise<unknown>) {
    event.preventDefault(); setError(''); setMessage('')
    const form = new FormData(event.currentTarget)
    try { await action(Object.fromEntries(form.entries())); event.currentTarget.reset(); await refresh() } catch (cause) { setError(cause instanceof Error ? cause.message : 'The access change could not be completed.') }
  }

  async function createRoleFromForm(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setError(''); setMessage('')
    const form = new FormData(event.currentTarget)
    const permissions = String(form.get('permissions')).split(/[,\n]/).map((item) => item.trim()).filter(Boolean)
    try { await createRole.mutateAsync({ code: form.get('code'), name: form.get('name'), description: form.get('description'), permissions }); event.currentTarget.reset(); await refresh() } catch (cause) { setError(cause instanceof Error ? cause.message : 'Role could not be created.') }
  }

  async function suspendUser(user: UserRow) {
    const reason = window.prompt(`Reason to suspend ${user.name}:`)?.trim() ?? ''
    if (reason.length < 8) return
    setError('')
    try { await suspend.mutateAsync({ id: user.id, reason }) } catch (cause) { setError(cause instanceof Error ? cause.message : 'Account could not be suspended.') }
  }

  const userItems = users.data?.data.data ?? []
  const roleItems = roles.data?.data.data ?? []
  return <section className="mx-auto max-w-6xl" aria-labelledby="iam-title">
    <p className="text-sm font-semibold text-[var(--color-brand-700)]">MODULE 02 · IDENTITY AND ACCESS</p><h1 id="iam-title" className="mt-1 text-3xl font-semibold">Users and roles</h1><p className="mt-3 max-w-3xl text-sm leading-6 text-[var(--color-muted)]">Account activation requires verified email, a strong password, and confirmed authenticator MFA. Role grants are scoped and require a separate workflow approval.</p>
    {error ? <p role="alert" className="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-800">{error}</p> : null}{message ? <p role="status" className="mt-4 rounded-md bg-emerald-50 p-3 text-sm text-emerald-900">{message}</p> : null}
    <div className="mt-6 grid gap-5 xl:grid-cols-2">
      <section className="rounded-xl border border-[var(--color-border)] bg-white p-5 shadow-sm"><h2 className="font-semibold">Invite user</h2><form className="mt-4 grid gap-3 sm:grid-cols-2" onSubmit={(event) => void submit(event, (payload) => invite.mutateAsync(payload))}><label className="text-sm font-medium">Name<Input name="name" required maxLength={255} /></label><label className="text-sm font-medium">Email<Input name="email" type="email" required /></label><label className="text-sm font-medium sm:col-span-2">Invitation reason<Input name="reason" required minLength={8} maxLength={500} /></label><div className="sm:col-span-2"><Button disabled={invite.isPending}>Send activation invitation</Button></div></form></section>
      <section className="rounded-xl border border-[var(--color-border)] bg-white p-5 shadow-sm"><h2 className="font-semibold">Create role</h2><form className="mt-4 space-y-3" onSubmit={(event) => void createRoleFromForm(event)}><div className="grid grid-cols-2 gap-3"><label className="text-sm font-medium">Role code<Input name="code" required /></label><label className="text-sm font-medium">Role name<Input name="name" required /></label></div><label className="block text-sm font-medium">Purpose<Input name="description" /></label><label className="block text-sm font-medium">Permission codes<textarea name="permissions" required rows={4} placeholder={'org.unit.view, org.unit.propose\nfinance.financial_year.view'} className="mt-1 w-full rounded-md border border-[var(--color-border)] p-3 font-mono text-xs" /></label><label className="block text-sm font-medium">Change reason<Input name="reason" required minLength={8} maxLength={500} /></label><Button disabled={createRole.isPending}>Create role</Button></form></section>
      <section className="rounded-xl border border-[var(--color-border)] bg-white p-5 shadow-sm xl:col-span-2"><h2 className="font-semibold">Request scoped role assignment</h2><form className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3" onSubmit={(event) => void submit(event, (data) => assign.mutateAsync({ id: Number(data.user_id), payload: { role_id: Number(data.role_id), scope_type: data.scope_type, scope_id: data.scope_id, effective_from: data.effective_from, reason: data.reason } }))}><label className="text-sm font-medium">User ID<Input name="user_id" type="number" required min={1} /></label><label className="text-sm font-medium">Role<select name="role_id" required className="mt-1 min-h-10 w-full rounded-md border border-[var(--color-border)] bg-white px-3 text-sm">{roleItems.map((role) => <option key={role.id} value={role.id}>{role.code} · {role.name}</option>)}</select></label><label className="text-sm font-medium">Scope type<Input name="scope_type" required defaultValue="global" /></label><label className="text-sm font-medium">Scope ID<Input name="scope_id" required defaultValue="global" /></label><label className="text-sm font-medium">Effective from<Input name="effective_from" type="datetime-local" required /></label><label className="text-sm font-medium">Reason<Input name="reason" minLength={8} required /></label><Button disabled={assign.isPending || !roleItems.length}>Submit assignment</Button></form></section>
    </div>
    <section className="mt-5 rounded-xl border border-[var(--color-border)] bg-white p-5 shadow-sm"><h2 className="font-semibold">Role assignments awaiting activation</h2><p className="mt-1 text-xs text-[var(--color-muted)]">A workflow must reach approved status before a second actor can activate the scoped assignment.</p><ul className="mt-3 divide-y divide-[var(--color-border)]">{(assignments.data?.data.items ?? []).map((assignment) => <li key={assignment.id} className="flex flex-wrap items-center justify-between gap-3 py-3"><div><p className="font-medium">{assignment.user_name} · {assignment.role_code}</p><p className="text-xs text-[var(--color-muted)]">{assignment.scope_type}:{assignment.scope_id} · workflow {assignment.workflow_status ?? 'unavailable'} · assignment {assignment.id}</p></div>{assignment.workflow_status === 'approved' ? <Button onClick={() => void activateAssignment.mutateAsync(assignment.id)} disabled={activateAssignment.isPending}>Activate assignment</Button> : null}</li>)}</ul></section>
    <section className="mt-5 overflow-hidden rounded-xl border border-[var(--color-border)] bg-white shadow-sm"><div className="border-b border-[var(--color-border)] p-5"><h2 className="font-semibold">User accounts</h2></div>{users.isLoading ? <p className="p-5 text-sm text-[var(--color-muted)]">Loading accounts…</p> : null}{users.isError ? <p className="p-5 text-sm text-red-800">{users.error.message}</p> : null}{userItems.length === 0 ? <p className="p-5 text-sm text-[var(--color-muted)]">No accounts found in your access scope.</p> : <ul className="divide-y divide-[var(--color-border)]">{userItems.map((user) => <li key={user.id} className="flex flex-wrap items-center justify-between gap-3 p-4"><div><p className="font-medium">{user.name} · {user.email}</p><p className="text-xs text-[var(--color-muted)]">ID {user.id} · {user.status} · MFA {user.mfa_enabled ? 'confirmed' : 'not enrolled'}</p></div>{user.status === 'active' ? <Button variant="destructive" onClick={() => void suspendUser(user)} disabled={suspend.isPending}>Suspend</Button> : null}</li>)}</ul>}</section>
  </section>
}
