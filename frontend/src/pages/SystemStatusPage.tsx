import { useQuery } from '@tanstack/react-query'
import { AlertTriangle, Check, LoaderCircle, RefreshCw } from 'lucide-react'
import { getReadiness } from '../services/health'

export function SystemStatusPage() {
  const status = useQuery({ queryKey: ['system-readiness'], queryFn: ({ signal }) => getReadiness(signal) })

  return (
    <section className="mx-auto max-w-3xl" aria-labelledby="status-title">
      <p className="mb-2 text-sm font-semibold text-[var(--color-brand-700)]">OPERATIONS</p><h1 id="status-title" className="text-3xl font-semibold tracking-tight">System status</h1><p className="mt-3 text-sm leading-6 text-[var(--color-muted)]">Readiness checks verify that the application can reach its configured dependencies. No connection details are displayed.</p>
      <div className="mt-7 rounded-xl border border-[var(--color-border)] bg-white p-6 shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="font-semibold">API readiness</h2><p className="mt-1 text-sm text-[var(--color-muted)]">GET /api/v1/health/ready</p></div><button type="button" onClick={() => void status.refetch()} className="inline-flex items-center gap-2 rounded-md border border-[var(--color-border)] px-3 py-2 text-sm font-medium hover:bg-slate-50" disabled={status.isFetching}><RefreshCw size={15} className={status.isFetching ? 'animate-spin' : ''} aria-hidden="true" />Refresh</button></div>
        {status.isLoading ? <p className="mt-6 flex items-center gap-2 text-sm text-[var(--color-muted)]"><LoaderCircle size={17} className="animate-spin" />Checking configured services…</p> : null}
        {status.isError ? <div role="alert" className="mt-6 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900"><AlertTriangle size={18} className="mt-0.5 shrink-0" /><div><p className="font-semibold">Readiness could not be confirmed</p><p className="mt-1">{status.error.message}. Start the API and check its local configuration, then refresh.</p></div></div> : null}
        {status.data ? <div className="mt-6"><p className={`flex items-center gap-2 text-sm font-semibold ${status.data.data.status === 'ready' ? 'text-[var(--color-success)]' : 'text-[var(--color-danger)]'}`}><Check size={17} aria-hidden="true" />{status.data.data.status === 'ready' ? 'Ready' : 'Not ready'}</p><ul className="mt-4 divide-y divide-[var(--color-border)]">{Object.entries(status.data.data.dependencies).map(([name, available]) => <li key={name} className="flex items-center justify-between py-3 text-sm"><span className="capitalize">{name}</span><span className={available ? 'font-medium text-[var(--color-success)]' : 'font-medium text-[var(--color-danger)]'}>{available ? 'Available' : 'Unavailable'}</span></li>)}</ul></div> : null}
      </div>
    </section>
  )
}
