import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { FormEvent } from 'react'
import { useState } from 'react'
import { Button } from '../components/ui/Button'
import { Input } from '../components/ui/Input'
import { apiGet, apiPost } from '../lib/api'

type Unit = { id: number; code: string; name: string; unit_type: string; status: string; version: number }
type Relationship = { id: number; parent_unit_id: number; child_unit_id: number; status: string }
type Page<T> = { data: T[] }

export function OrganizationPage() {
  const client = useQueryClient()
  const [error, setError] = useState('')
  const units = useQuery({ queryKey: ['org-units'], queryFn: ({ signal }) => apiGet<Page<Unit>>('/organization/units', signal) })
  const relationships = useQuery({ queryKey: ['org-relationships'], queryFn: ({ signal }) => apiGet<Page<Relationship>>('/organization/relationships', signal) })
  const refresh = async () => { await Promise.all([client.invalidateQueries({ queryKey: ['org-units'] }), client.invalidateQueries({ queryKey: ['org-relationships'] })]) }
  const createUnit = useMutation({ mutationFn: (data: object) => apiPost('/organization/units', data), onSuccess: refresh })
  const submitUnit = useMutation({ mutationFn: (id: number) => apiPost(`/organization/units/${id}/submit`), onSuccess: refresh })
  const activateUnit = useMutation({ mutationFn: (id: number) => apiPost(`/organization/units/${id}/activate`), onSuccess: refresh })
  const createRelationship = useMutation({ mutationFn: (data: object) => apiPost('/organization/relationships', data), onSuccess: refresh })
  const submitRelationship = useMutation({ mutationFn: (id: number) => apiPost(`/organization/relationships/${id}/submit`), onSuccess: refresh })
  const activateRelationship = useMutation({ mutationFn: (id: number) => apiPost(`/organization/relationships/${id}/activate`), onSuccess: refresh })

  async function handleForm(event: FormEvent<HTMLFormElement>, submit: (data: Record<string, FormDataEntryValue>) => Promise<unknown>) {
    event.preventDefault()
    setError('')
    const form = new FormData(event.currentTarget)
    try {
      await submit(Object.fromEntries(form.entries()))
      event.currentTarget.reset()
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'The change could not be saved.') }
  }

  async function run(action: () => Promise<unknown>) {
    setError('')
    try { await action() } catch (cause) { setError(cause instanceof Error ? cause.message : 'The action could not be completed.') }
  }

  const unitItems = units.data?.data.data ?? []
  const relationItems = relationships.data?.data.data ?? []
  return <section className="mx-auto max-w-6xl" aria-labelledby="org-title">
    <p className="text-sm font-semibold text-[var(--color-brand-700)]">MODULE 03 · COUNTY ORGANIZATION</p><h1 id="org-title" className="mt-1 text-3xl font-semibold">Organization structure</h1>
    <p className="mt-3 max-w-3xl text-sm leading-6 text-[var(--color-muted)]">Configure a generic, effective-dated hierarchy. Unit types and county structure are not pre-seeded; approved changes retain their history.</p>
    {error ? <p role="alert" className="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-800">{error}</p> : null}
    <div className="mt-6 grid gap-5 xl:grid-cols-2">
      <section className="rounded-xl border border-[var(--color-border)] bg-white p-5 shadow-sm"><h2 className="font-semibold">Propose organization unit</h2>
        <form className="mt-4 grid gap-3 sm:grid-cols-2" onSubmit={(event) => void handleForm(event, (data) => createUnit.mutateAsync(data))}>
          <label className="text-sm font-medium">Code<Input name="code" required maxLength={100} /></label><label className="text-sm font-medium">Name<Input name="name" required maxLength={200} /></label>
          <label className="text-sm font-medium">Unit type<Input name="unit_type" required maxLength={80} placeholder="Configured type" /></label><label className="text-sm font-medium">Effective from<Input type="date" name="effective_from" /></label>
          <label className="text-sm font-medium">Effective to<Input type="date" name="effective_to" /></label><div className="flex items-end"><Button disabled={createUnit.isPending}>Save draft</Button></div>
        </form>
      </section>
      <section className="rounded-xl border border-[var(--color-border)] bg-white p-5 shadow-sm"><h2 className="font-semibold">Propose hierarchy relationship</h2>
        <form className="mt-4 grid gap-3 sm:grid-cols-2" onSubmit={(event) => void handleForm(event, (data) => createRelationship.mutateAsync(data))}>
          <label className="text-sm font-medium">Parent unit ID<Input type="number" name="parent_unit_id" min={1} required /></label><label className="text-sm font-medium">Child unit ID<Input type="number" name="child_unit_id" min={1} required /></label>
          <label className="text-sm font-medium">Effective from<Input type="date" name="effective_from" required /></label><label className="text-sm font-medium">Effective to<Input type="date" name="effective_to" /></label>
          <div className="sm:col-span-2"><Button disabled={createRelationship.isPending}>Save relationship draft</Button></div>
        </form>
      </section>
    </div>
    <div className="mt-6 grid gap-5 xl:grid-cols-2">
      <section className="overflow-hidden rounded-xl border border-[var(--color-border)] bg-white shadow-sm"><div className="border-b border-[var(--color-border)] p-5"><h2 className="font-semibold">Organization units</h2></div>{units.isLoading ? <p className="p-5 text-sm text-[var(--color-muted)]">Loading units…</p> : null}{units.isError ? <p className="p-5 text-sm text-red-800">{units.error.message}</p> : null}{unitItems.length === 0 ? <p className="p-5 text-sm text-[var(--color-muted)]">No units configured.</p> : <ul className="divide-y divide-[var(--color-border)]">{unitItems.map((unit) => <li key={unit.id} className="flex flex-wrap items-center justify-between gap-3 p-4"><div><p className="font-medium">{unit.name} <span className="font-normal text-[var(--color-muted)]">({unit.code})</span></p><p className="text-xs text-[var(--color-muted)]">ID {unit.id} · {unit.unit_type} · {unit.status} · v{unit.version}</p></div><div className="flex gap-2">{unit.status === 'draft' ? <Button variant="secondary" onClick={() => void run(() => submitUnit.mutateAsync(unit.id))}>Submit</Button> : null}{unit.status === 'in_review' ? <Button onClick={() => void run(() => activateUnit.mutateAsync(unit.id))}>Activate approved</Button> : null}</div></li>)}</ul>}</section>
      <section className="overflow-hidden rounded-xl border border-[var(--color-border)] bg-white shadow-sm"><div className="border-b border-[var(--color-border)] p-5"><h2 className="font-semibold">Hierarchy relationships</h2></div>{relationships.isLoading ? <p className="p-5 text-sm text-[var(--color-muted)]">Loading relationships…</p> : null}{relationItems.length === 0 ? <p className="p-5 text-sm text-[var(--color-muted)]">No relationships configured.</p> : <ul className="divide-y divide-[var(--color-border)]">{relationItems.map((edge) => <li key={edge.id} className="flex flex-wrap items-center justify-between gap-3 p-4"><div><p className="font-medium">Unit {edge.parent_unit_id} → unit {edge.child_unit_id}</p><p className="text-xs text-[var(--color-muted)]">Relationship {edge.id} · {edge.status}</p></div><div className="flex gap-2">{edge.status === 'draft' ? <Button variant="secondary" onClick={() => void run(() => submitRelationship.mutateAsync(edge.id))}>Submit</Button> : null}{edge.status === 'in_review' ? <Button onClick={() => void run(() => activateRelationship.mutateAsync(edge.id))}>Activate approved</Button> : null}</div></li>)}</ul>}</section>
    </div>
  </section>
}
