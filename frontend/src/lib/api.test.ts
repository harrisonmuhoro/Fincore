import { afterEach, describe, expect, it, vi } from 'vitest'
import { apiGet } from './api'

afterEach(() => vi.unstubAllGlobals())

describe('API client', () => {
  it('returns the standard successful response envelope', async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ success: true, message: 'ok', data: { status: 'ready' }, meta: { request_id: 'test-id' } }), { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)
    vi.stubGlobal('crypto', { randomUUID: () => 'client-request-id' })

    await expect(apiGet<{ status: string }>('/health/ready')).resolves.toMatchObject({ success: true, data: { status: 'ready' } })
    expect(fetchMock).toHaveBeenCalledWith(expect.stringMatching(/\/api\/v1\/health\/ready$/), expect.objectContaining({ credentials: 'include', headers: expect.objectContaining({ 'X-Request-ID': 'client-request-id' }) }))
  })

  it('normalizes structured API failures into ApiError', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify({ success: false, message: 'The given data was invalid.', data: null, errors: { name: ['Required.'] } }), { status: 422 })))
    vi.stubGlobal('crypto', { randomUUID: () => 'client-request-id' })

    await expect(apiGet('/validation')).rejects.toMatchObject({ name: 'ApiError', message: 'The given data was invalid.', status: 422, errors: { name: ['Required.'] } })
  })

  it('reports non-JSON responses without exposing raw response content', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('<html>internal error</html>', { status: 500 })))
    vi.stubGlobal('crypto', { randomUUID: () => 'client-request-id' })

    await expect(apiGet('/health/ready')).rejects.toMatchObject({ name: 'ApiError', message: 'The server returned an unreadable response.', status: 500 })
  })
})
