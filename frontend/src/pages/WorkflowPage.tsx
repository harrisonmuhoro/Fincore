import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import type { FormEvent } from 'react'
import { Button } from '../components/ui/Button'
import { Input } from '../components/ui/Input'
import { apiGet, apiPost } from '../lib/api'

type ApprovalItem = { id: number; definition_code: string; definition_version: number; subject_type: string; subject_id: string; status: string; current_step: number; requested_by: number; created_at: string; decisions: Array<{ decision: string; reason: string | null; actor_id: number; decided_at: string }> }
type WorkflowQueue = { items: ApprovalItem[]; bounded: boolean }
type Definition = { id: number; code: string; name: string; version: number; status: string; created_by: number | null; steps: Array<{ name: string; approver_reference: string }> }

export function WorkflowPage() {
  const client = useQueryClient()
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const queue = useQuery({ queryKey: ['workflow-queue'], queryFn: ({ signal }) => apiGet<WorkflowQueue>('/workflows', signal), refetchInterval: 30_000 })
  const definitions = useQuery({ queryKey: ['workflow-definitions'], queryFn: ({ signal }) => apiGet<{ data: Definition[] }>('/workflow-definitions', signal) })
  const refresh = async () => { await client.invalidateQueries({ queryKey: ['workflow-queue'] }) }
  const create = useMutation({ mutationFn: (payload: object) => apiPost('/workflow-definitions', payload), onSuccess: async () => { setMessage('Draft workflow saved. A different workflow owner must publish it.'); await refresh() } })
  const publish = useMutation({ mutationFn: (id: number) => apiPost(`/workflow-definitions/${id}/publish`), onSuccess: async () => { await client.invalidateQueries({ queryKey: ['workflow-definitions'] }) } })
  const decide = useMutation({ mutationFn: ({ id, decision, reason }: { id: number; decision: string; reason: string }) => apiPost(`/workflows/${id}/decisions`, { decision, reason }), onSuccess: refresh })
  const delegate = useMutation({ mutationFn: (payload: object) => apiPost('/workflow-delegations', payload), onSuccess: () => setMessage('Time-bounded delegation recorded.') })

  async function createDefinition(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError('')
    const form = new FormData(event.currentTarget)
    const permissions = String(form.get('permissions') ?? '').split('\n').map((value) => value.trim()).filter(Boolean)
    try {
      await create.mutateAsync({ code: form.get('code'), name: form.get('name'), steps: permissions.map((permission, index) => ({ name: `Approval step ${index + 1}`, permission })) })
      event.currentTarget.reset()
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Workflow definition could not be saved.') }
  }

  async function createDelegation(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError('')
    const form = new FormData(event.currentTarget)
    try {
      await delegate.mutateAsync(Object.fromEntries(form.entries()))
      event.currentTarget.reset()
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Delegation could not be created.') }
  }

  async function decideItem(id: number, decision: string) {
    const reason = decision === 'approve' ? 'Reviewed against the configured workflow step.' : window.prompt(`Reason to ${decision} this item:`)?.trim() ?? ''
    if (decision !== 'approve' && reason.length < 8) return
    setError('')
    try { await decide.mutateAsync({ id, decision, reason }) } catch (cause) { setError(cause instanceof Error ? cause.message : 'Decision was not recorded.') }
  }

  async function publishDefinition(id: number) {
    setError('')
    try { await publish.mutateAsync(id); setMessage('Workflow version published.') } catch (cause) { setError(cause instanceof Error ? cause.message : 'Workflow could not be published.') }
  }

  const items = queue.data?.data.items ?? []
  return <section className="mx-auto max-w-6xl" aria-labelledby="workflow-title">
    <p className="text-sm font-semibold text-[var(--color-brand-700)]">MODULE 04 · WORKFLOW ENGINE</p><h1 id="workflow-title" className="mt-1 text-3xl font-semibold">Approvals and workflow setup</h1>
    <p className="mt-3 max-w-3xl text-sm leading-6 text-[var(--color-muted)]">Workflow definitions pin each submission to a version. Approval roles and thresholds are configured by authorized owners; no monetary limits are preloaded.</p>
    {message ? <p role="status" className="mt-4 rounded-md bg-emerald-50 p-3 text-sm text-emerald-900">{message}</p> : null}{error ? <p role="alert" className="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-800">{error}</p> : null}
    <div className="mt-6 grid gap-5 xl:grid-cols-[1.2fr_0.8fr]">
      <section className="rounded-xl border border-[var(--color-border)] bg-white p-5 shadow-sm"><div className="flex items-center justify-between"><h2 className="font-semibold">My approval queue</h2><Button variant="secondary" onClick={() => void queue.refetch()} disabled={queue.isFetching}>Refresh</Button></div>
        {queue.isError ? <p role="alert" className="mt-4 text-sm text-red-800">{queue.error.message}</p> : null}{queue.isLoading ? <p className="mt-4 text-sm text-[var(--color-muted)]">Loading approval queue…</p> : null}
        {items.length === 0 && !queue.isLoading ? <p className="mt-4 rounded-md bg-slate-50 p-4 text-sm text-[var(--color-muted)]">No approvals are currently assigned to your permissions or active delegations.</p> : null}
        <ul className="mt-4 divide-y divide-[var(--color-border)]">{items.map((item) => <li key={item.id} className="py-4"><div className="flex flex-wrap items-start justify-between gap-3"><div><p className="font-semibold">{item.definition_code} · {item.subject_type} {item.subject_id}</p><p className="mt-1 text-xs text-[var(--color-muted)]">Request {item.id} · step {item.current_step} · requester {item.requested_by} · {new Date(item.created_at).toLocaleString()}</p></div><span className="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-900">{item.status}</span></div>
          {item.decisions.length ? <ol className="mt-3 space-y-1 text-xs text-[var(--color-muted)]">{item.decisions.map((decision, index) => <li key={`${item.id}:${index}`}>Step: {decision.decision} by user {decision.actor_id}{decision.reason ? ` — ${decision.reason}` : ''}</li>)}</ol> : null}
          {item.status === 'pending' ? <div className="mt-3 flex gap-2"><Button onClick={() => void decideItem(item.id, 'approve')} disabled={decide.isPending}>Approve</Button><Button variant="secondary" onClick={() => void decideItem(item.id, 'return')} disabled={decide.isPending}>Return</Button><Button variant="destructive" onClick={() => void decideItem(item.id, 'reject')} disabled={decide.isPending}>Reject</Button></div> : null}
        </li>)}</ul>
      </section>
      <div className="space-y-5">
        <section className="rounded-xl border border-[var(--color-border)] bg-white p-5 shadow-sm"><h2 className="font-semibold">Create a versioned workflow</h2><form className="mt-4 space-y-3" onSubmit={(event) => void createDefinition(event)}><label className="block text-sm font-medium">Workflow code<Input name="code" required maxLength={100} /></label><label className="block text-sm font-medium">Name<Input name="name" required maxLength={200} /></label><label className="block text-sm font-medium">Approval permissions, one per step<textarea name="permissions" required rows={4} placeholder={'finance.financial_year.activate\nworkflow.instance.approve'} className="mt-1 w-full rounded-md border border-[var(--color-border)] p-3 text-sm" /></label><p className="text-xs leading-5 text-[var(--color-muted)]">Every step must name a registered permission. Publishing requires a different authorized user from the author.</p><Button disabled={create.isPending}>Save draft</Button></form></section>
        <section className="rounded-xl border border-[var(--color-border)] bg-white p-5 shadow-sm"><h2 className="font-semibold">Workflow definitions</h2>{definitions.isLoading ? <p className="mt-3 text-sm text-[var(--color-muted)]">Loading definitions…</p> : null}<ul className="mt-3 divide-y divide-[var(--color-border)]">{(definitions.data?.data.data ?? []).map((definition) => <li key={definition.id} className="flex items-start justify-between gap-3 py-3"><div><p className="text-sm font-semibold">{definition.name} <span className="font-normal">v{definition.version}</span></p><p className="mt-1 text-xs text-[var(--color-muted)]">{definition.code} · {definition.status} · {definition.steps.length} steps</p></div>{definition.status === 'draft' ? <Button variant="secondary" onClick={() => void publishDefinition(definition.id)} disabled={publish.isPending}>Publish</Button> : null}</li>)}</ul></section>
        <section className="rounded-xl border border-[var(--color-border)] bg-white p-5 shadow-sm"><h2 className="font-semibold">Delegate approval authority</h2><form className="mt-4 space-y-3" onSubmit={(event) => void createDelegation(event)}><label className="block text-sm font-medium">Delegate user ID<Input name="delegate_id" type="number" min={1} required /></label><label className="block text-sm font-medium">Permission code<Input name="permission" required placeholder="finance.financial_year.activate" /></label><div className="grid grid-cols-2 gap-3"><label className="text-sm font-medium">Scope type<Input name="scope_type" required defaultValue="global" /></label><label className="text-sm font-medium">Scope ID<Input name="scope_id" required defaultValue="global" /></label></div><div className="grid grid-cols-2 gap-3"><label className="text-sm font-medium">Starts<Input type="datetime-local" name="effective_from" required /></label><label className="text-sm font-medium">Ends<Input type="datetime-local" name="effective_to" required /></label></div><label className="block text-sm font-medium">Reason<Input name="reason" minLength={8} required /></label><Button variant="secondary" disabled={delegate.isPending}>Create delegation</Button></form></section>
      </div>
    </div>
  </section>
}
