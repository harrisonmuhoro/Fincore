import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ActivateAccountPage } from './ActivateAccountPage'
import { MfaSetupPage } from './MfaSetupPage'
import { SignInPage } from './SignInPage'

afterEach(() => {
  sessionStorage.clear()
  vi.unstubAllGlobals()
})

beforeEach(() => sessionStorage.clear())

describe('identity pages', () => {
  it('requires authenticator setup before the workspace can be entered', () => {
    sessionStorage.setItem('fincore.access-token', 'test-token')
    render(<MemoryRouter initialEntries={['/mfa-setup']}><Routes><Route path="/mfa-setup" element={<MfaSetupPage />} /><Route path="/sign-in" element={<SignInPage />} /><Route path="/" element={<p>Workspace</p>} /></Routes></MemoryRouter>)

    expect(screen.getByRole('heading', { name: 'Set up an authenticator' })).toBeInTheDocument()
    expect(screen.getByText(/account stays restricted until an authenticator code is confirmed/i)).toBeInTheDocument()
  })

  it('activates an invited account through the API and displays the response', async () => {
    vi.stubGlobal('crypto', { randomUUID: () => 'test-request-id' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify({ success: true, message: 'Account activated. Sign in to enroll an authenticator before accessing the system.', data: { status: 'verified' } }), { status: 200 })))
    render(<MemoryRouter><ActivateAccountPage /></MemoryRouter>)
    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'staff@example.test' } })
    fireEvent.change(screen.getByLabelText('Invitation token'), { target: { value: 'activation-token' } })
    fireEvent.change(screen.getByLabelText('New password'), { target: { value: 'StrongPassword123!' } })
    fireEvent.change(screen.getByLabelText('Confirm password'), { target: { value: 'StrongPassword123!' } })
    fireEvent.click(screen.getByRole('button', { name: 'Set password and activate' }))

    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Account activated.'))
  })
})
