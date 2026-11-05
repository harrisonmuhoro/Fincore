const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL ?? 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '')

export type ApiEnvelope<T> = {
  success: boolean
  message: string
  data: T
  meta?: { request_id?: string }
  errors?: Record<string, string[]>
}

const tokenKey = 'fincore.access-token'

export function saveAccessToken(token: string): void {
  sessionStorage.setItem(tokenKey, token)
}

export function clearAccessToken(): void {
  sessionStorage.removeItem(tokenKey)
}

export function hasAccessToken(): boolean {
  return Boolean(sessionStorage.getItem(tokenKey))
}

export class ApiError extends Error {
  readonly status: number
  readonly errors: Record<string, string[]> | undefined

  constructor(message: string, status: number, errors?: Record<string, string[]>) {
    super(message)
    this.status = status
    this.errors = errors
    this.name = 'ApiError'
  }
}

async function apiRequest<T>(path: string, method: string, payload?: unknown, signal?: AbortSignal): Promise<ApiEnvelope<T>> {
  const token = sessionStorage.getItem(tokenKey)
  const response = await fetch(`${apiBaseUrl}${path}`, {
    method,
    headers: {
      Accept: 'application/json',
      'X-Request-ID': crypto.randomUUID(),
      ...(payload ? { 'Content-Type': 'application/json' } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    credentials: 'include',
    ...(payload ? { body: JSON.stringify(payload) } : {}),
    ...(signal ? { signal } : {}),
  })

  let body: ApiEnvelope<T> | undefined
  try {
    body = await response.json() as ApiEnvelope<T>
  } catch {
    throw new ApiError('The server returned an unreadable response.', response.status)
  }

  if (!response.ok || !body.success) {
    throw new ApiError(body.message || 'The request failed.', response.status, body.errors)
  }

  return body
}

export function apiGet<T>(path: string, signal?: AbortSignal): Promise<ApiEnvelope<T>> {
  return apiRequest<T>(path, 'GET', undefined, signal)
}

export function apiPost<T>(path: string, payload?: unknown): Promise<ApiEnvelope<T>> {
  return apiRequest<T>(path, 'POST', payload)
}

export function apiPatch<T>(path: string, payload: unknown): Promise<ApiEnvelope<T>> {
  return apiRequest<T>(path, 'PATCH', payload)
}
