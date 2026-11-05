import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import type { FormEvent } from 'react'
import { Button } from '../components/ui/Button'
import { Input } from '../components/ui/Input'
import { apiGet, apiPost } from '../lib/api'

type Page<T> = { data: T[] }
type Calendar = { id: number; code: string; name: string; status: string }
type FinancialYear = { id: number; calendar_id: number; code: string; starts_on: string; ends_on: string; status: string; approval_workflow_id: string | null }
type Period = { id: number; financial_year_id: number; code: string; starts_on: string; ends_on: string; status: string }
type MasterVersion = { id: number; domain: string; version: number; status: string; approval_workflow_id: string | null }
type Account = { id: number; code: string; name: string; account_type: string; status: string; parent_id: number | null }

export function FinancialWorkspacePage() {
  const client = useQueryClient()
  const [selectedVersion, setSelectedVersion] = useState('')
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const calendars = useQuery({ queryKey: ['finance-calendars'], queryFn: ({ signal }) => apiGet<Page<Calendar>>('/finance/calendars', signal) })
  const years = useQuery({ queryKey: ['finance-years'], queryFn: ({ signal }) => apiGet<Page<FinancialYear>>('/finance/financial-years', signal) })
  const periods = useQuery({ queryKey: ['finance-periods'], queryFn: ({ signal }) => apiGet<Page<Period>>('/finance/accounting-periods', signal) })
  const versions = useQuery({ queryKey: ['finance-master-versions'], queryFn: ({ signal }) => apiGet<Page<MasterVersion>>('/finance/master-data/versions', signal) })
  const accounts = useQuery({ queryKey: ['finance-accounts', selectedVersion], enabled: Boolean(selectedVersion), queryFn: ({ signal }) => apiGet<Page<Account>>(`/finance/master-data/versions/${selectedVersion}/accounts`, signal) })
  const currencies = useQuery({ queryKey: ['finance-currencies'], queryFn: ({ signal }) => apiGet<Array<{ code: string; name: string; decimal_places: number }>>('/finance/currencies', signal) })
  const refresh = async () => { await Promise.all(['finance-calendars', 'finance-years', 'finance-periods', 'finance-master-versions', 'finance-accounts'].map((key) => client.invalidateQueries({ queryKey: [key] }))) }
  const createCalendar = useMutation({ mutationFn: (payload: object) => apiPost('/finance/calendars', payload), onSuccess: refresh })
  const createYear = useMutation({ mutationFn: (payload: object) => apiPost('/finance/financial-years', payload), onSuccess: refresh })
  const activateYear = useMutation({ mutationFn: (id: number) => apiPost(`/finance/financial-years/${id}/activate`), onSuccess: refresh })
  const createVersion = useMutation({ mutationFn: () => apiPost('/finance/master-data/versions', { domain: 'finance_master' }), onSuccess: refresh })
  const createAccount = useMutation({ mutationFn: ({ version, payload }: { version: string; payload: object }) => apiPost(`/finance/master-data/versions/${version}/accounts`, payload), onSuccess: refresh })
  const activateVersion = useMutation({ mutationFn: (id: number) => apiPost(`/finance/master-data/versions/${id}/activate`), onSuccess: refresh })
  const openPeriod = useMutation({ mutationFn: (id: number) => apiPost(`/finance/accounting-periods/${id}/open`), onSuccess: refresh })
  const reopenPeriod = useMutation({ mutationFn: (id: number) => apiPost(`/finance/accounting-periods/${id}/reopen`, { reason: 'Authorized period reopen request.' }), onSuccess: refresh })
  const closePeriod = useMutation({ mutationFn: (id: number) => apiPost(`/finance/accounting-periods/${id}/close`), onSuccess: refresh })

  async function handle(event: FormEvent<HTMLFormElement>, action: (payload: Record<string, FormDataEntryValue>) => Promise<unknown>) {
    event.preventDefault()
    setError('')
    setMessage('')
    const form = new FormData(event.currentTarget)
    try { await action(Object.fromEntries(form.entries())); event.currentTarget.reset(); await refresh() } catch (cause) { setError(cause instanceof Error ? cause.message : 'The financial setup action failed.') }
  }

  async function createFinancialYear(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError('')
    const form = new FormData(event.currentTarget)
    let configuredPeriods: unknown
    try { configuredPeriods = JSON.parse(String(form.get('periods'))) } catch { setError('Periods must be valid JSON.'); return }
    try {
      await createYear.mutateAsync({ calendar_id: Number(form.get('calendar_id')), code: form.get('code'), starts_on: form.get('starts_on'), ends_on: form.get('ends_on'), periods: configuredPeriods })
      setMessage('Financial year draft created. Dates and period layout came from your input.'); event.currentTarget.reset(); await refresh()
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Financial year could not be created.') }
  }

  async function run(action: () => Promise<unknown>, success?: string) {
    setError('')
    try { await action(); if (success) setMessage(success); await refresh() } catch (cause) { setError(cause instanceof Error ? cause.message : 'The request could not be completed.') }
  }

  const calendarItems = calendars.data?.data.data ?? []
  const yearItems = years.data?.data.data ?? []
  const periodItems = periods.data?.data.data ?? []
  const versionItems = versions.data?.data.data ?? []
  const accountItems = accounts.data?.data.data ?? []
  const activeVersion = versionItems.find((version) => String(version.id) === selectedVersion)
  return <section className="mx-auto max-w-6xl" aria-labelledby="finance-title">
    <p className="text-sm font-semibold text-[var(--color-brand-700)]">MODULE 05 · FINANCIAL MASTER DATA</p><h1 id="finance-title" className="mt-1 text-3xl font-semibold">Financial setup</h1>
    <p className="mt-3 max-w-4xl text-sm leading-6 text-[var(--color-muted)]">Configure chart references and calendars. No account taxonomy, accounting basis, fiscal dates, period count or approval limit is assumed. A closed period has a separate reopen path; posting stays blocked unless a period is open.</p>
    {error ? <p role="alert" className="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-800">{error}</p> : null}{message ? <p role="status" className="mt-4 rounded-md bg-emerald-50 p-3 text-sm text-emerald-900">{message}</p> : null}
    <div className="mt-6 grid gap-5 xl:grid-cols-2">
      <section className="rounded-xl border border-[var(--color-border)] bg-white p-5 shadow-sm"><h2 className="font-semibold">Financial calendars</h2><form className="mt-4 grid gap-3 sm:grid-cols-2" onSubmit={(event) => void handle(event, (payload) => createCalendar.mutateAsync(payload))}><label className="text-sm font-medium">Calendar code<Input name="code" maxLength={100} required /></label><label className="text-sm font-medium">Calendar name<Input name="name" maxLength={200} required /></label><Button disabled={createCalendar.isPending}>Create calendar</Button></form><ul className="mt-4 divide-y divide-[var(--color-border)]">{calendarItems.map((calendar) => <li key={calendar.id} className="py-2 text-sm"><span className="font-medium">{calendar.name}</span> · {calendar.code} · {calendar.status} · ID {calendar.id}</li>)}</ul></section>
      <section className="rounded-xl border border-[var(--color-border)] bg-white p-5 shadow-sm"><h2 className="font-semibold">Financial year draft</h2><form className="mt-4 space-y-3" onSubmit={(event) => void createFinancialYear(event)}><label className="block text-sm font-medium">Calendar<select name="calendar_id" required className="mt-1 min-h-10 w-full rounded-md border border-[var(--color-border)] bg-white px-3 text-sm">{calendarItems.map((calendar) => <option key={calendar.id} value={calendar.id}>{calendar.code} · {calendar.name}</option>)}</select></label><div className="grid grid-cols-2 gap-3"><label className="text-sm font-medium">Year code<Input name="code" maxLength={100} required /></label><label className="text-sm font-medium">Starts<Input type="date" name="starts_on" required /></label><label className="text-sm font-medium">Ends<Input type="date" name="ends_on" required /></label></div><label className="block text-sm font-medium">Period layout JSON<textarea name="periods" required rows={4} placeholder={'[{"code":"P1","starts_on":"YYYY-MM-DD","ends_on":"YYYY-MM-DD"}]'} className="mt-1 w-full rounded-md border border-[var(--color-border)] p-3 font-mono text-xs" /></label><p className="text-xs text-[var(--color-muted)]">Periods must cover the dates you enter without overlaps or gaps. Choose dates and period count from the approved local calendar.</p><Button disabled={!calendarItems.length || createYear.isPending}>Save year draft</Button></form></section>
    </div>
    <section className="mt-5 overflow-hidden rounded-xl border border-[var(--color-border)] bg-white shadow-sm"><div className="border-b border-[var(--color-border)] p-5"><h2 className="font-semibold">Financial years and accounting periods</h2></div><div className="grid gap-6 p-5 lg:grid-cols-2"><div><h3 className="text-sm font-semibold">Years</h3>{yearItems.length === 0 ? <p className="mt-2 text-sm text-[var(--color-muted)]">No year calendars configured.</p> : <ul className="mt-2 divide-y divide-[var(--color-border)]">{yearItems.map((year) => <li key={year.id} className="flex flex-wrap items-center justify-between gap-3 py-3"><div><p className="font-medium">{year.code} · {year.status}</p><p className="text-xs text-[var(--color-muted)]">{year.starts_on} — {year.ends_on} · calendar {year.calendar_id}</p></div>{year.status === 'draft' ? <Button variant="secondary" onClick={() => void run(() => activateYear.mutateAsync(year.id), 'Year activation submitted or completed. Use the workflow queue for independent review.')}>Submit activation</Button> : null}</li>)}</ul>}</div><div><h3 className="text-sm font-semibold">Periods</h3>{periodItems.length === 0 ? <p className="mt-2 text-sm text-[var(--color-muted)]">No periods configured.</p> : <ul className="mt-2 divide-y divide-[var(--color-border)]">{periodItems.map((period) => <li key={period.id} className="flex flex-wrap items-center justify-between gap-3 py-3"><div><p className="font-medium">{period.code} · {period.status}</p><p className="text-xs text-[var(--color-muted)]">{period.starts_on} — {period.ends_on}</p></div><div className="flex gap-2">{period.status === 'future' ? <Button variant="secondary" onClick={() => void run(() => openPeriod.mutateAsync(period.id), 'Period opening submitted or completed.')}>Open</Button> : null}{period.status === 'closed' ? <Button variant="secondary" onClick={() => void run(() => reopenPeriod.mutateAsync(period.id), 'Period reopen submitted or completed.')}>Request reopen</Button> : null}{period.status === 'open' ? <Button variant="secondary" onClick={() => void run(() => closePeriod.mutateAsync(period.id))}>Close</Button> : null}</div></li>)}</ul>}<p className="mt-3 text-xs leading-5 text-[var(--color-muted)]">Close is held until the General Ledger (Module 13) supplies reconciliation readiness evidence.</p></div></div></section>
    <div className="mt-5 grid gap-5 xl:grid-cols-[0.8fr_1.2fr]">
      <section className="rounded-xl border border-[var(--color-border)] bg-white p-5 shadow-sm"><h2 className="font-semibold">Active currency metadata</h2><ul className="mt-3 divide-y divide-[var(--color-border)]">{(currencies.data?.data ?? []).map((currency) => <li key={currency.code} className="py-2 text-sm"><span className="font-semibold">{currency.code}</span> · {currency.name} · {currency.decimal_places} decimals</li>)}</ul><p className="mt-3 text-xs text-[var(--color-muted)]">KES is seeded as the documented demonstration default. Additional currency policy requires finance approval.</p></section>
      <section className="rounded-xl border border-[var(--color-border)] bg-white p-5 shadow-sm"><div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="font-semibold">Chart of accounts version</h2><p className="mt-1 text-xs text-[var(--color-muted)]">Master data becomes immutable after independent activation.</p></div><Button variant="secondary" onClick={() => void run(() => createVersion.mutateAsync(), 'Finance master version created as draft.')}>New master version</Button></div><div className="mt-4 flex flex-wrap items-end gap-3"><label className="text-sm font-medium">Select version<select className="mt-1 block min-h-10 rounded-md border border-[var(--color-border)] bg-white px-3 text-sm" value={selectedVersion} onChange={(event) => setSelectedVersion(event.target.value)}><option value="">Choose version</option>{versionItems.map((version) => <option key={version.id} value={version.id}>v{version.version} · {version.status} (ID {version.id})</option>)}</select></label>{activeVersion?.status === 'draft' ? <Button onClick={() => void run(() => activateVersion.mutateAsync(activeVersion.id), 'Activation submitted or completed after independent approval.')}>Submit activation</Button> : null}</div>
        {activeVersion ? <form className="mt-5 grid gap-3 sm:grid-cols-2" onSubmit={(event) => void handle(event, (payload) => createAccount.mutateAsync({ version: selectedVersion, payload }))}><label className="text-sm font-medium">Account code<Input name="code" required maxLength={100} /></label><label className="text-sm font-medium">Account name<Input name="name" required maxLength={200} /></label><label className="text-sm font-medium">Account type<Input name="account_type" required maxLength={80} placeholder="Configured taxonomy" /></label><label className="text-sm font-medium">Parent ID (optional)<Input name="parent_id" type="number" min={1} /></label><Button disabled={activeVersion.status !== 'draft' || createAccount.isPending}>Save account draft</Button></form> : null}
        {selectedVersion ? <div className="mt-5"><h3 className="text-sm font-semibold">Accounts in selected version</h3>{accountItems.length ? <ul className="mt-2 divide-y divide-[var(--color-border)]">{accountItems.map((account) => <li key={account.id} className="py-2 text-sm">{account.code} · {account.name} · {account.account_type} · {account.status} · ID {account.id}</li>)}</ul> : <p className="mt-2 text-sm text-[var(--color-muted)]">No accounts in this version.</p>}</div> : null}
      </section>
    </div>
  </section>
}
