import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost, apiPut } from '../lib/api'
import { Button } from '../components/ui/Button'
import { Input } from '../components/ui/Input'

type Page<T> = { data: T[]; current_page: number; last_page: number }
type DocumentRecord = { id: number; title: string; classification: string; sensitivity: string; status: string; legal_hold: boolean; versions: { version: number; original_name: string; scan_status: string; size_bytes: number }[] }
type InboxItem = { id: number; title: string; body: string; read_at: string | null; created_at: string }
type SecurityEvent = { id: number; category: string; severity: string; actor_id: number | null; occurred_at: string }
type Incident = { id: number; title: string; severity: string; status: string; version: number }
type Announcement = { id: number; title: string; body: string; status: string; created_by: number; published_at: string | null }
type Preference = { id: number; category: string; in_app_enabled: boolean; email_enabled: boolean; version: number }
type MonitoringSnapshot = { id: number; check_type: string; status: string; source: string; summary: string; checked_at: string }

export function SharedControlsPage() {
  const client = useQueryClient()
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')
  const [title, setTitle] = useState('')
  const [file, setFile] = useState<File>()
  const [incidentTitle, setIncidentTitle] = useState('')
  const [incidentDescription, setIncidentDescription] = useState('')
  const [severity, setSeverity] = useState('medium')
  const [announcementTitle, setAnnouncementTitle] = useState('')
  const [announcementBody, setAnnouncementBody] = useState('')
  const [monitorSummary, setMonitorSummary] = useState('')
  const [monitorSource, setMonitorSource] = useState('')
  const [monitorType, setMonitorType] = useState('application')
  const [monitorStatus, setMonitorStatus] = useState('ok')
  const [preferenceCategory, setPreferenceCategory] = useState('workflow')
  const [inAppEnabled, setInAppEnabled] = useState(true)
  const [emailEnabled, setEmailEnabled] = useState(false)
  const docs = useQuery({ queryKey: ['documents'], queryFn: async ({ signal }) => (await apiGet<Page<DocumentRecord>>('/documents', signal)).data })
  const inbox = useQuery({ queryKey: ['notifications'], queryFn: async ({ signal }) => (await apiGet<Page<InboxItem>>('/notifications?per_page=20', signal)).data })
  const events = useQuery({ queryKey: ['security-events'], queryFn: async ({ signal }) => (await apiGet<Page<SecurityEvent>>('/security/events?per_page=20', signal)).data })
  const incidents = useQuery({ queryKey: ['security-incidents'], queryFn: async ({ signal }) => (await apiGet<Page<Incident>>('/security/incidents', signal)).data })
  const announcements = useQuery({ queryKey: ['announcements'], queryFn: async ({ signal }) => (await apiGet<Page<Announcement>>('/announcements', signal)).data })
  const preferences = useQuery({ queryKey: ['notification-preferences'], queryFn: async ({ signal }) => (await apiGet<Preference[]>('/notification-preferences', signal)).data })
  const snapshots = useQuery({ queryKey: ['monitoring-snapshots'], queryFn: async ({ signal }) => (await apiGet<Page<MonitoringSnapshot>>('/security/monitoring-snapshots', signal)).data })

  async function run(action: () => Promise<unknown>, message: string) {
    setError('')
    setNotice('')
    try {
      await action()
      setNotice(message)
      await Promise.all([client.invalidateQueries({ queryKey: ['documents'] }), client.invalidateQueries({ queryKey: ['notifications'] }), client.invalidateQueries({ queryKey: ['security-events'] }), client.invalidateQueries({ queryKey: ['security-incidents'] }), client.invalidateQueries({ queryKey: ['announcements'] }), client.invalidateQueries({ queryKey: ['notification-preferences'] }), client.invalidateQueries({ queryKey: ['monitoring-snapshots'] })])
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'The action could not be completed.')
    }
  }

  async function upload(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!file) return
    const form = new FormData()
    form.set('file', file)
    form.set('title', title)
    await run(() => apiPost('/documents', form), 'Document uploaded to private quarantine. Downloads stay blocked until an authorized scan result is recorded.')
    setTitle('')
    setFile(undefined)
    event.currentTarget.reset()
  }

  async function openIncident(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    await run(() => apiPost('/security/incidents', { title: incidentTitle, description: incidentDescription, severity }), 'Security incident opened.')
    setIncidentTitle('')
    setIncidentDescription('')
  }

  async function createAnnouncement(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    await run(() => apiPost('/announcements', { title: announcementTitle, body: announcementBody }), 'Announcement draft saved. A different authorized user must publish it.')
    setAnnouncementTitle('')
    setAnnouncementBody('')
  }

  async function savePreference(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const existing = preferences.data?.find((preference) => preference.category === preferenceCategory)
    await run(() => apiPut('/notification-preferences', { category: preferenceCategory, in_app_enabled: inAppEnabled, email_enabled: emailEnabled, ...(existing ? { expected_version: existing.version } : {}) }), 'Notification preference saved.')
  }

  async function recordSnapshot(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    await run(() => apiPost('/security/monitoring-snapshots', { check_type: monitorType, status: monitorStatus, source: monitorSource, summary: monitorSummary, checked_at: new Date().toISOString() }), 'Monitoring snapshot recorded.')
    setMonitorSummary('')
    setMonitorSource('')
  }

  return <div className="space-y-7">
    <header><p className="text-sm font-semibold text-[var(--color-brand-700)]">SHARED CONTROLS · MODULES 23, 24, 26</p><h1 className="mt-2 text-2xl font-semibold">Documents, notifications and security</h1><p className="mt-2 text-sm text-[var(--color-muted)]">Private records, personal inbox and append-only security evidence. Access is checked by the backend.</p></header>
    {notice ? <p role="status" className="rounded-md bg-emerald-50 p-3 text-sm text-emerald-900">{notice}</p> : null}{error ? <p role="alert" className="rounded-md bg-red-50 p-3 text-sm text-red-900">{error}</p> : null}

    <section className="rounded-xl border border-[var(--color-border)] bg-white p-5 shadow-sm"><h2 className="font-semibold">Upload a private document</h2><p className="mt-1 text-sm text-[var(--color-muted)]">Uploads are quarantined by default. No file can be downloaded until an authorized scanner records a clean result.</p><form className="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto]" onSubmit={(event) => void upload(event)}><label className="text-sm">Document title<Input required maxLength={200} value={title} onChange={(event) => setTitle(event.target.value)} /></label><label className="text-sm">File<Input required type="file" accept=".pdf,.png,.jpg,.jpeg,.txt,.csv,.docx,.xlsx" onChange={(event) => setFile(event.target.files?.[0])} /></label><Button className="self-end" disabled={!file}>Upload</Button></form>
      <div className="mt-4 divide-y divide-[var(--color-border)]">{docs.isLoading ? <p className="py-3 text-sm">Loading documents…</p> : null}{docs.isError ? <p role="alert" className="py-3 text-sm text-red-800">{docs.error.message}</p> : null}{(docs.data?.data ?? []).map((document) => <div key={document.id} className="flex flex-wrap items-center justify-between gap-3 py-3"><div><p className="font-medium">{document.title}</p><p className="text-xs text-[var(--color-muted)]">{document.classification} · {document.sensitivity} · {document.status}{document.legal_hold ? ' · legal hold' : ''} · v{document.versions[0]?.version ?? 1} · scan {document.versions[0]?.scan_status ?? 'pending'}</p></div></div>)}{!docs.isLoading && (docs.data?.data.length ?? 0) === 0 ? <p className="py-3 text-sm text-[var(--color-muted)]">No documents available in your scope.</p> : null}</div>
    </section>

    <section className="rounded-xl border border-[var(--color-border)] bg-white p-5 shadow-sm"><div className="flex items-center justify-between"><h2 className="font-semibold">My notifications</h2><Button variant="secondary" onClick={() => void inbox.refetch()}>Refresh</Button></div><div className="mt-3 divide-y divide-[var(--color-border)]">{(inbox.data?.data ?? []).map((item) => <div key={item.id} className="flex items-start justify-between gap-4 py-3"><div><p className="font-medium">{item.title}{!item.read_at ? <span className="ml-2 rounded bg-blue-50 px-2 py-0.5 text-xs text-blue-800">New</span> : null}</p><p className="mt-1 text-sm text-[var(--color-muted)]">{item.body}</p></div>{!item.read_at ? <Button variant="secondary" onClick={() => void run(() => apiPost(`/notifications/${item.id}/read`), 'Notification marked as read.')}>Mark read</Button> : null}</div>)}{!inbox.isLoading && (inbox.data?.data.length ?? 0) === 0 ? <p className="py-3 text-sm text-[var(--color-muted)]">Your inbox is clear.</p> : null}</div></section>

    <section className="rounded-xl border border-[var(--color-border)] bg-white p-5 shadow-sm"><h2 className="font-semibold">Notification preferences</h2><form className="mt-3 flex flex-wrap items-end gap-4" onSubmit={(event) => void savePreference(event)}><label className="text-sm">Category<select className="mt-1 block rounded-md border border-[var(--color-border)] p-2" value={preferenceCategory} onChange={(event) => { setPreferenceCategory(event.target.value); const current = preferences.data?.find((preference) => preference.category === event.target.value); setInAppEnabled(current?.in_app_enabled ?? true); setEmailEnabled(current?.email_enabled ?? false) }}>{['workflow', 'announcement', 'security', 'finance'].map((category) => <option key={category}>{category}</option>)}</select></label><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={inAppEnabled} onChange={(event) => setInAppEnabled(event.target.checked)} />In-app</label><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={emailEnabled} onChange={(event) => setEmailEnabled(event.target.checked)} />Email</label><Button disabled={!inAppEnabled && !emailEnabled}>Save preference</Button></form></section>

    <section className="rounded-xl border border-[var(--color-border)] bg-white p-5 shadow-sm"><h2 className="font-semibold">Announcements</h2><form className="mt-4 grid gap-3 sm:grid-cols-2" onSubmit={(event) => void createAnnouncement(event)}><label className="text-sm">Announcement title<Input required maxLength={200} value={announcementTitle} onChange={(event) => setAnnouncementTitle(event.target.value)} /></label><label className="text-sm">Announcement text<textarea className="mt-1 min-h-20 w-full rounded-md border border-[var(--color-border)] p-3 text-sm" required maxLength={10000} value={announcementBody} onChange={(event) => setAnnouncementBody(event.target.value)} /></label><Button className="sm:col-span-2 sm:justify-self-start">Save draft</Button></form><ul className="mt-4 divide-y divide-[var(--color-border)]">{(announcements.data?.data ?? []).map((item) => <li key={item.id} className="py-3"><p className="font-medium">{item.title}<span className="ml-2 text-xs text-[var(--color-muted)]">{item.status}</span></p><p className="mt-1 text-sm text-[var(--color-muted)]">{item.body}</p>{item.status === 'published' && item.published_at ? <p className="mt-1 text-xs text-[var(--color-muted)]">Published {new Date(item.published_at).toLocaleString()}</p> : null}{item.status === 'draft' ? <Button className="mt-2" variant="secondary" onClick={() => void run(() => apiPost(`/announcements/${item.id}/publish`), 'Announcement published and delivery queued.')}>Publish (independent reviewer)</Button> : null}</li>)}</ul></section>

    <section className="rounded-xl border border-[var(--color-border)] bg-white p-5 shadow-sm"><h2 className="font-semibold">Operational monitoring snapshots</h2><p className="mt-1 text-sm text-[var(--color-muted)]">Record authenticated check results. This register does not run backup or infrastructure probes itself.</p><form className="mt-4 grid gap-3 sm:grid-cols-4" onSubmit={(event) => void recordSnapshot(event)}><label className="text-sm">Check<select className="mt-1 block w-full rounded-md border border-[var(--color-border)] p-2" value={monitorType} onChange={(event) => setMonitorType(event.target.value)}>{['application', 'queue', 'storage', 'backup'].map((value) => <option key={value}>{value}</option>)}</select></label><label className="text-sm">Status<select className="mt-1 block w-full rounded-md border border-[var(--color-border)] p-2" value={monitorStatus} onChange={(event) => setMonitorStatus(event.target.value)}>{['ok', 'warning', 'failed'].map((value) => <option key={value}>{value}</option>)}</select></label><label className="text-sm">Source<Input required maxLength={100} value={monitorSource} onChange={(event) => setMonitorSource(event.target.value)} /></label><label className="text-sm">Summary<Input required maxLength={500} value={monitorSummary} onChange={(event) => setMonitorSummary(event.target.value)} /></label><Button className="sm:col-span-4 sm:justify-self-start">Record check</Button></form><ul className="mt-4 divide-y divide-[var(--color-border)]">{(snapshots.data?.data ?? []).map((snapshot) => <li key={snapshot.id} className="py-2 text-sm"><span className="font-medium">{snapshot.check_type} · {snapshot.status}</span><span className="ml-2 text-[var(--color-muted)]">{snapshot.source} · {snapshot.summary}</span></li>)}</ul></section>

    <section className="grid gap-6 xl:grid-cols-2"><div className="rounded-xl border border-[var(--color-border)] bg-white p-5 shadow-sm"><h2 className="font-semibold">Open a security incident</h2><form className="mt-4 space-y-3" onSubmit={(event) => void openIncident(event)}><label className="block text-sm">Title<Input required maxLength={200} value={incidentTitle} onChange={(event) => setIncidentTitle(event.target.value)} /></label><label className="block text-sm">Description<textarea className="mt-1 min-h-24 w-full rounded-md border border-[var(--color-border)] p-3 text-sm" required maxLength={5000} value={incidentDescription} onChange={(event) => setIncidentDescription(event.target.value)} /></label><label className="block text-sm">Severity<select className="mt-1 block w-full rounded-md border border-[var(--color-border)] p-2" value={severity} onChange={(event) => setSeverity(event.target.value)}>{['low', 'medium', 'high', 'critical'].map((value) => <option key={value}>{value}</option>)}</select></label><Button>Open incident</Button></form><h3 className="mt-5 text-sm font-semibold">Incident register</h3><ul className="mt-2 divide-y divide-[var(--color-border)]">{(incidents.data?.data ?? []).map((incident) => <li key={incident.id} className="py-2 text-sm"><span className="font-medium">{incident.title}</span><span className="ml-2 text-[var(--color-muted)]">{incident.severity} · {incident.status}</span></li>)}</ul></div>
      <div className="rounded-xl border border-[var(--color-border)] bg-white p-5 shadow-sm"><div className="flex items-center justify-between"><h2 className="font-semibold">Security events</h2><Button variant="secondary" onClick={() => void events.refetch()}>Refresh</Button></div><ul className="mt-3 divide-y divide-[var(--color-border)]">{(events.data?.data ?? []).map((event) => <li key={event.id} className="py-3 text-sm"><p className="font-medium">{event.category} <span className="ml-1 rounded bg-slate-100 px-2 py-0.5 text-xs">{event.severity}</span></p><p className="mt-1 text-xs text-[var(--color-muted)]">Actor {event.actor_id ?? 'unknown'} · {new Date(event.occurred_at).toLocaleString()}</p></li>)}{!events.isLoading && (events.data?.data.length ?? 0) === 0 ? <li className="py-3 text-sm text-[var(--color-muted)]">No security events in scope.</li> : null}</ul></div></section>
  </div>
}
