import { Navigate, Route, Routes } from 'react-router-dom'
import type { ReactNode } from 'react'
import { AppLayout } from './layouts/AppLayout'
import { ActivateAccountPage } from './pages/ActivateAccountPage'
import { FinancialWorkspacePage } from './pages/FinancialWorkspacePage'
import { HomePage } from './pages/HomePage'
import { MfaSetupPage } from './pages/MfaSetupPage'
import { NotFoundPage } from './pages/NotFoundPage'
import { OrganizationPage } from './pages/OrganizationPage'
import { SettingsPage } from './pages/SettingsPage'
import { SignInPage } from './pages/SignInPage'
import { SystemStatusPage } from './pages/SystemStatusPage'
import { WorkflowPage } from './pages/WorkflowPage'
import { hasAccessToken } from './lib/api'

function PrivatePage({ children }: { children: ReactNode }) {
  return hasAccessToken() ? children : <Navigate to="/sign-in" replace />
}

export function App() {
  return (
    <Routes>
      <Route element={<AppLayout />}>
        <Route index element={<HomePage />} />
        <Route path="system-status" element={<SystemStatusPage />} />
        <Route path="sign-in" element={<SignInPage />} />
        <Route path="activate-account" element={<ActivateAccountPage />} />
        <Route path="mfa-setup" element={<MfaSetupPage />} />
        <Route path="system-settings" element={<PrivatePage><SettingsPage /></PrivatePage>} />
        <Route path="organization" element={<PrivatePage><OrganizationPage /></PrivatePage>} />
        <Route path="workflows" element={<PrivatePage><WorkflowPage /></PrivatePage>} />
        <Route path="finance" element={<PrivatePage><FinancialWorkspacePage /></PrivatePage>} />
        <Route path="*" element={<Navigate to="/not-found" replace />} />
        <Route path="not-found" element={<NotFoundPage />} />
      </Route>
    </Routes>
  )
}
