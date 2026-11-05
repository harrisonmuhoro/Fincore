import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, within } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { App } from './App'

function renderApp(initialEntry = '/') {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(<QueryClientProvider client={queryClient}><MemoryRouter initialEntries={[initialEntry]}><App /></MemoryRouter></QueryClientProvider>)
}

describe('application shell', () => {
  it('renders the overview and working status navigation', () => {
    renderApp()
    expect(screen.getByRole('heading', { name: 'FinCore County' })).toBeInTheDocument()
    expect(within(screen.getByRole('navigation', { name: 'Main navigation' })).getByRole('link', { name: /system status/i })).toHaveAttribute('href', '/system-status')
  })

  it('renders the status route', () => {
    renderApp('/system-status')
    expect(screen.getByRole('heading', { name: 'System status' })).toBeInTheDocument()
    expect(screen.getByText('GET /api/v1/health/ready')).toBeInTheDocument()
  })

  it('shows a useful not-found page for unknown routes', () => {
    renderApp('/unknown-area')
    expect(screen.getByRole('heading', { name: 'That page is unavailable' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Return to overview' })).toHaveAttribute('href', '/')
  })
})
