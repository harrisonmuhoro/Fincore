import { Link } from 'react-router-dom'

export function NotFoundPage() {
  return <section className="mx-auto max-w-2xl py-12 text-center"><p className="text-sm font-semibold text-[var(--color-brand-700)]">404 · PAGE NOT FOUND</p><h1 className="mt-3 text-3xl font-semibold">That page is unavailable</h1><p className="mt-3 text-sm text-[var(--color-muted)]">The address may be incorrect or the page may have moved.</p><Link to="/" className="mt-6 inline-flex rounded-md bg-[var(--color-brand-950)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-brand-800)]">Return to overview</Link></section>
}
