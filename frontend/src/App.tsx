import { Navigate, Route, Routes } from 'react-router-dom'
import { AppLayout } from './layouts/AppLayout'
import { HomePage } from './pages/HomePage'
import { NotFoundPage } from './pages/NotFoundPage'
import { SystemStatusPage } from './pages/SystemStatusPage'

export function App() {
  return (
    <Routes>
      <Route element={<AppLayout />}>
        <Route index element={<HomePage />} />
        <Route path="system-status" element={<SystemStatusPage />} />
        <Route path="*" element={<Navigate to="/not-found" replace />} />
        <Route path="not-found" element={<NotFoundPage />} />
      </Route>
    </Routes>
  )
}
