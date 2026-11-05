import { Banknote, CircleHelp, LayoutDashboard, Landmark, LogIn, LogOut, Settings, ShieldCheck, Users, Workflow } from 'lucide-react'
import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { apiPost, clearAccessToken, hasAccessToken } from '../lib/api'

const navigation = [
  { label: 'Overview', to: '/', icon: LayoutDashboard },
  { label: 'System status', to: '/system-status', icon: ShieldCheck },
  { label: 'Organization', to: '/organization', icon: Users, private: true },
  { label: 'Workflow', to: '/workflows', icon: Workflow, private: true },
  { label: 'Financial setup', to: '/finance', icon: Landmark, private: true },
  { label: 'System settings', to: '/system-settings', icon: Settings, private: true },
]

export function AppLayout() {
  const navigate = useNavigate()
  const authenticated = hasAccessToken()

  async function logout() {
    try { await apiPost('/auth/logout') } catch { /* Clearing the local session still signs the browser out. */ } finally {
      clearAccessToken()
      navigate('/sign-in', { replace: true })
    }
  }

  return (
    <div className="min-h-screen bg-[var(--color-canvas)] text-[var(--color-ink)]">
      <header className="border-b border-[var(--color-border)] bg-white">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-8">
          <NavLink to="/" className="flex items-center gap-3 rounded-md" aria-label="FinCore County home">
            <span className="flex size-10 items-center justify-center rounded-lg bg-[var(--color-brand-950)] text-white"><Banknote size={21} aria-hidden="true" /></span>
            <span><span className="block text-sm font-semibold tracking-wide">FINCORE COUNTY</span><span className="block text-xs text-[var(--color-muted)]">Public financial management</span></span>
          </NavLink>
          <div className="flex items-center gap-3"><span className="hidden items-center gap-2 text-xs font-medium text-[var(--color-muted)] sm:flex"><CircleHelp size={15} aria-hidden="true" /> Foundation modules</span>{authenticated ? <button type="button" onClick={() => void logout()} className="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium hover:bg-slate-100"><LogOut size={16} aria-hidden="true" />Sign out</button> : <NavLink to="/sign-in" className="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium hover:bg-slate-100"><LogIn size={16} aria-hidden="true" />Sign in</NavLink>}</div>
        </div>
      </header>
      <div className="mx-auto grid min-h-[calc(100vh-73px)] max-w-7xl md:grid-cols-[220px_1fr]">
        <aside className="border-b border-[var(--color-border)] bg-white p-4 md:border-b-0 md:border-r md:p-5">
          <p className="mb-3 px-3 text-[11px] font-semibold uppercase tracking-[0.14em] text-[var(--color-muted)]">Workspace</p>
          <nav aria-label="Main navigation" className="flex gap-2 md:flex-col">
            {navigation.filter((item) => !item.private || authenticated).map(({ label, to, icon: Icon }) => <NavLink key={to} end={to === '/'} to={to} className={({ isActive }) => `flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition-colors ${isActive ? 'bg-[var(--color-brand-100)] text-[var(--color-brand-950)]' : 'text-[var(--color-muted)] hover:bg-slate-50 hover:text-[var(--color-ink)]'}`}><Icon size={17} aria-hidden="true" />{label}</NavLink>)}
          </nav>
        </aside>
        <main className="min-w-0 px-5 py-8 sm:px-8 lg:px-10"><Outlet /></main>
      </div>
      <footer className="border-t border-[var(--color-border)] bg-white px-5 py-4 text-center text-xs text-[var(--color-muted)]">FinCore County · Platform foundation · No financial transactions are enabled</footer>
    </div>
  )
}
