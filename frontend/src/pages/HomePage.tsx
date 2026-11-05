import { ArrowRight, BookOpen, CircleCheck, Server } from 'lucide-react'
import { Link } from 'react-router-dom'

const foundation = ['Account invitations, MFA and scoped role permissions', 'Audited platform settings and atomic reference numbers', 'Organization hierarchy with independent approval', 'Configurable approvals and versioned financial setup']

export function HomePage() {
  return (
    <div className="mx-auto max-w-5xl">
      <div className="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div><p className="mb-2 text-sm font-semibold text-[var(--color-brand-700)]">PLATFORM FOUNDATION</p><h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">FinCore County</h1><p className="mt-3 max-w-2xl text-base leading-7 text-[var(--color-muted)]">Foundation modules support identity, organization, approval workflows and financial master data. Transaction processing is not enabled.</p></div>
        <span className="rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-900">Foundation modules</span>
      </div>
      <section aria-labelledby="foundation-heading" className="rounded-xl border border-[var(--color-border)] bg-white p-6 shadow-sm sm:p-8">
        <div className="flex items-center gap-3"><span className="flex size-10 items-center justify-center rounded-lg bg-[var(--color-brand-100)] text-[var(--color-brand-800)]"><CircleCheck size={21} aria-hidden="true" /></span><div><h2 id="foundation-heading" className="text-lg font-semibold">Development foundation</h2><p className="text-sm text-[var(--color-muted)]">Core application services are available for subsequent modules.</p></div></div>
        <ul className="mt-6 grid gap-3 sm:grid-cols-2">{foundation.map((item) => <li key={item} className="flex items-start gap-2.5 text-sm leading-6"><CircleCheck size={17} className="mt-1 shrink-0 text-[var(--color-success)]" aria-hidden="true" />{item}</li>)}</ul>
      </section>
      <div className="mt-6 grid gap-4 sm:grid-cols-2">
        <Link to="/system-status" className="group rounded-xl border border-[var(--color-border)] bg-white p-5 transition hover:border-[var(--color-brand-700)]"><span className="flex items-center justify-between"><span className="flex size-9 items-center justify-center rounded-md bg-slate-100"><Server size={18} aria-hidden="true" /></span><ArrowRight size={17} className="text-[var(--color-muted)] transition group-hover:translate-x-1" aria-hidden="true" /></span><span className="mt-4 block font-semibold">System status</span><span className="mt-1 block text-sm text-[var(--color-muted)]">Check API and required dependency readiness.</span></Link>
        <a href="https://github.com/harrisonmuhoro/Fincore-" target="_blank" rel="noreferrer" className="group rounded-xl border border-[var(--color-border)] bg-white p-5 transition hover:border-[var(--color-brand-700)]"><span className="flex items-center justify-between"><span className="flex size-9 items-center justify-center rounded-md bg-slate-100"><BookOpen size={18} aria-hidden="true" /></span><ArrowRight size={17} className="text-[var(--color-muted)] transition group-hover:translate-x-1" aria-hidden="true" /></span><span className="mt-4 block font-semibold">Project documentation</span><span className="mt-1 block text-sm text-[var(--color-muted)]">Architecture, module boundaries, and delivery standards.</span></a>
      </div>
    </div>
  )
}
