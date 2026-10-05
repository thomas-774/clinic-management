import { Navigate, Route, Routes } from 'react-router-dom'
import { homePathFor, useAuth } from './auth/useAuth'
import GuestRoute from './auth/GuestRoute'
import ProtectedRoute from './auth/ProtectedRoute'
import RoleRoute from './auth/RoleRoute'
import FullPageLoader from './components/FullPageLoader'
import PagePlaceholder from './components/PagePlaceholder'
import AssistantLayout from './layouts/AssistantLayout'
import DoctorLayout from './layouts/DoctorLayout'
import PatientLayout from './layouts/PatientLayout'
import AssistantPatientPage from './pages/assistant/PatientPage'
import AssistantToday from './pages/assistant/Today'
import Login from './pages/auth/Login'
import Register from './pages/auth/Register'
import Dashboard from './pages/doctor/Dashboard'
import PatientDetails from './pages/doctor/PatientDetails'
import PatientsList from './pages/doctor/PatientsList'
import PrescriptionForm from './pages/doctor/PrescriptionForm'
import Reports from './pages/doctor/Reports'
import Schedule from './pages/doctor/Schedule'
import Settings from './pages/doctor/Settings'
import VisitForm from './pages/doctor/VisitForm'
import BookAppointment from './pages/patient/BookAppointment'
import MyAppointments from './pages/patient/MyAppointments'
import PatientHome from './pages/patient/PatientHome'
import Status from './pages/Status'

/** "/" sends each user to their own area, or to the login page. */
function HomeRedirect() {
  const { user, loading } = useAuth()
  if (loading) return <FullPageLoader />
  return <Navigate to={user ? homePathFor(user.role) : '/login'} replace />
}

/** Every route from §7.2. */
export default function AppRoutes() {
  return (
    <Routes>
      <Route path="/" element={<HomeRedirect />} />
      <Route path="/status" element={<Status />} />

      <Route element={<GuestRoute />}>
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
      </Route>

      <Route element={<ProtectedRoute />}>
        <Route path="/patient" element={<RoleRoute role="patient" />}>
          <Route element={<PatientLayout />}>
            <Route index element={<PatientHome />} />
            <Route path="book" element={<BookAppointment />} />
            <Route path="appointments" element={<MyAppointments />} />
          </Route>
        </Route>

        <Route path="/doctor" element={<RoleRoute role="doctor" />}>
          <Route element={<DoctorLayout />}>
            <Route index element={<Dashboard />} />
            <Route path="schedule" element={<Schedule />} />
            <Route path="patients" element={<PatientsList />} />
            <Route path="patients/:id" element={<PatientDetails />} />
            <Route path="visits/new" element={<VisitForm />} />
            <Route path="patients/:id/prescriptions/new" element={<PrescriptionForm />} />
            <Route path="prescriptions/:id/edit" element={<PrescriptionForm editing />} />
            {/* Replaced by the print page (outside the layout) in T9-11. */}
            <Route path="prescriptions/:id/print" element={<PagePlaceholder title="Prescription" />} />
            <Route path="settings" element={<Settings />} />
            <Route path="reports" element={<Reports />} />
          </Route>
        </Route>

        <Route path="/assistant" element={<RoleRoute role="assistant" />}>
          <Route element={<AssistantLayout />}>
            <Route index element={<AssistantToday />} />
            <Route path="patients" element={<PatientsList />} />
            <Route path="patients/:id" element={<AssistantPatientPage />} />
            <Route path="schedule" element={<Schedule />} />
          </Route>
        </Route>
      </Route>

      <Route path="*" element={<HomeRedirect />} />
    </Routes>
  )
}
