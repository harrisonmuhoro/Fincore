import { apiGet } from '../lib/api'

export type Readiness = { status: 'ready' | 'not_ready'; dependencies: Record<string, boolean> }

export function getReadiness(signal?: AbortSignal) {
  return apiGet<Readiness>('/health/ready', signal)
}
