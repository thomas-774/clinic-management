import { screen, waitFor } from '@testing-library/react'
import { vi } from 'vitest'
import * as authApi from '../api/auth'
import * as doctorPatientsApi from '../api/doctorPatients'
import { tokenStorage } from '../api/client'
import { expectPath, renderAppAt as renderAt } from '../test/renderApp'

const patient = { id: 2, name: 'Mona', role: 'patient', patient_id: 1 }
const doctor = { id: 1, name: 'Dr. Doctor', role: 'doctor' }

describe('route guards', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.restoreAllMocks()
  })

  it('sends a logged-out visitor to /login', async () => {
    renderAt('/doctor/patients')
    await expectPath('/login')
  })

  it('sends a patient who opens /doctor to /patient', async () => {
    tokenStorage.set('patient-token')
    vi.spyOn(authApi, 'me').mockResolvedValue(patient)

    renderAt('/doctor')

    expect(await screen.findByRole('link', { name: 'My appointments' })).toBeInTheDocument()
    await expectPath('/patient')
  })

  it('sends a doctor who opens /patient/book to /doctor', async () => {
    tokenStorage.set('doctor-token')
    vi.spyOn(authApi, 'me').mockResolvedValue(doctor)

    renderAt('/patient/book')

    expect(await screen.findByRole('heading', { name: 'Dashboard' })).toBeInTheDocument()
    await expectPath('/doctor')
  })

  it('lets each role open its own pages', async () => {
    tokenStorage.set('doctor-token')
    vi.spyOn(authApi, 'me').mockResolvedValue(doctor)

    const getPatient = vi.spyOn(doctorPatientsApi, 'getPatient').mockReturnValue(new Promise(() => {}))

    renderAt('/doctor/patients/12')

    await expectPath('/doctor/patients/12')
    await waitFor(() => expect(getPatient).toHaveBeenCalledWith(12))
  })

  it('sends a logged-in user away from /login to their home', async () => {
    tokenStorage.set('doctor-token')
    vi.spyOn(authApi, 'me').mockResolvedValue(doctor)

    renderAt('/login')

    await screen.findByRole('heading', { name: 'Dashboard' })
    await expectPath('/doctor')
  })

  it('shows a loader while checking the stored token', async () => {
    tokenStorage.set('slow-token')
    let resolve
    vi.spyOn(authApi, 'me').mockReturnValue(new Promise((r) => (resolve = r)))

    renderAt('/patient')

    expect(screen.getByRole('status')).toBeInTheDocument()
    resolve(patient)
    expect(await screen.findByRole('link', { name: 'My appointments' })).toBeInTheDocument()
  })

  it('treats a rejected stored token as logged out', async () => {
    tokenStorage.set('bad-token')
    vi.spyOn(authApi, 'me').mockRejectedValue(new Error('401'))

    renderAt('/patient')

    await expectPath('/login')
  })
})
