import { Navigate, Outlet } from 'react-router-dom'
import { homePathFor, useAuth } from './useAuth'
import FullPageLoader from '../components/FullPageLoader'

/** For /login and /register: a logged-in user goes straight to their home. */
export default function GuestRoute() {
  const { user, loading } = useAuth()

  if (loading) return <FullPageLoader />
  if (user) return <Navigate to={homePathFor(user.role)} replace />

  return <Outlet />
}
