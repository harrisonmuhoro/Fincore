const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL ?? 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '')

export type ApiEnvelope<T> = {
  success: boolean
  message: string
  data: T
  meta?: { request_id?: string }
  errors?: Record<string, string[]>
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

export async function apiGet<T>(path: string, signal?: AbortSignal): Promise<ApiEnvelope<T>> {
  const response = await fetch(`${apiBaseUrl}${path}`, {
    method: 'GET',
    headers: { Accept: 'application/json', 'X-Request-ID': crypto.randomUUID() },
    credentials: 'include',
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
