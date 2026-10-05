import { BrowserRouter } from 'react-router-dom'
import AppRoutes from './AppRoutes'
import { AuthProvider } from './auth/AuthContext'

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <AppRoutes />
      </AuthProvider>
    </BrowserRouter>
  )
}
