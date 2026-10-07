import { lazy } from 'react'

/**
 * Each role's pages are separate chunks (NFR-P.4, T11-10): a patient never
 * downloads the doctor's pages, and Recharts (Reports only) stays out of the
 * first load. Login and Register stay in the main chunk.
 */
const shared = {
  PatientsList: () => import('./doctor/PatientsList'),
  Schedule: () => import('./doctor/Schedule'),
}

const loaders = {
  patient: {
    PatientHome: () => import('./patient/PatientHome'),
    BookAppointment: () => import('./patient/BookAppointment'),
    MyAppointments: () => import('./patient/MyAppointments'),
  },
  doctor: {
    Dashboard: () => import('./doctor/Dashboard'),
    PatientDetails: () => import('./doctor/PatientDetails'),
    VisitForm: () => import('./doctor/VisitForm'),
    PrescriptionForm: () => import('./doctor/PrescriptionForm'),
    PrescriptionPrintPage: () => import('./doctor/PrescriptionPrintPage'),
    Settings: () => import('./doctor/Settings'),
    Reports: () => import('./doctor/Reports'),
    ...shared,
  },
  assistant: {
    AssistantToday: () => import('./assistant/Today'),
    AssistantPatientPage: () => import('./assistant/PatientPage'),
    ...shared,
  },
}

/**
 * Starts downloading every page of `role` (right after login), so the first
 * click does not wait for its chunk. A failed download is ignored here;
 * opening the page asks for the chunk again.
 */
export function prefetchRolePages(role) {
  return Promise.all(Object.values(loaders[role] ?? {}).map((load) => load().catch(() => null)))
}

export const PatientHome = lazy(loaders.patient.PatientHome)
export const BookAppointment = lazy(loaders.patient.BookAppointment)
export const MyAppointments = lazy(loaders.patient.MyAppointments)

export const Dashboard = lazy(loaders.doctor.Dashboard)
export const PatientDetails = lazy(loaders.doctor.PatientDetails)
export const VisitForm = lazy(loaders.doctor.VisitForm)
export const PrescriptionForm = lazy(loaders.doctor.PrescriptionForm)
export const PrescriptionPrintPage = lazy(loaders.doctor.PrescriptionPrintPage)
export const Settings = lazy(loaders.doctor.Settings)
export const Reports = lazy(loaders.doctor.Reports)
export const PatientsList = lazy(shared.PatientsList)
export const Schedule = lazy(shared.Schedule)

export const AssistantToday = lazy(loaders.assistant.AssistantToday)
export const AssistantPatientPage = lazy(loaders.assistant.AssistantPatientPage)
