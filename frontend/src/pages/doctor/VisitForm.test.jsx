import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { AxiosError } from 'axios'
import { vi } from 'vitest'
import * as appointmentsApi from '../../api/appointments'
import * as authApi from '../../api/auth'
import { tokenStorage } from '../../api/client'
import * as patientsApi from '../../api/doctorPatients'
import * as visitsApi from '../../api/visits'
import { expectPath, renderAppAt } from '../../test/renderApp'

const MONA = { id: 7, name: 'Mona Ali', phone: '01011112222' }
const APPOINTMENT = {
  id: 40,
  start_at: '2026-10-05T17:00:00+03:00',
  end_at: '2026-10-05T17:45:00+03:00',
  status: 'checked_in',
  patient: MONA,
}

function fakeServer() {
  vi.spyOn(appointmentsApi, 'getSchedule').mockResolvedValue([APPOINTMENT])
  vi.spyOn(patientsApi, 'getPatient').mockResolvedValue({
    ...MONA,
    email: null,
    address: 'Cairo',
    date_of_birth: null,
    gender: null,
    current_illness: null,
    history: [],
    visits: [],
    outstanding_balance: '500.00',
  })
  return vi.spyOn(visitsApi, 'createVisit').mockImplementation(async (fields) => ({
    id: 88,
    total_amount: '1500.00',
    paid: '1000.00',
    remaining: '500.00',
    payment_status: 'partially_paid',
    ...fields,
  }))
}

function renderForm(path) {
  tokenStorage.set('t')
  vi.spyOn(authApi, 'me').mockResolvedValue({ id: 1, name: 'Dr. Doctor', role: 'doctor' })
  return renderAppAt(path)
}

describe('VisitForm', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.restoreAllMocks()
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-10-05T14:20:00Z')) // 17:20 in Cairo
  })

  afterEach(() => vi.useRealTimers())

  it('shows the patient and appointment time (found in today’s schedule)', async () => {
    fakeServer()
    renderForm('/doctor/visits/new?appointment=40')

    expect(await screen.findByText('Mona Ali')).toBeInTheDocument()
    expect(screen.getByText(/Appointment: 5 Oct 2026/)).toHaveTextContent('17:00 – 17:45')
    expect(appointmentsApi.getSchedule).toHaveBeenCalledWith({ from: '2026-10-05', to: '2026-10-05' })
  })

  it('typing 1500 / 1000 shows 500 in red, and saving creates the visit', async () => {
    const create = fakeServer()
    renderForm('/doctor/visits/new?appointment=40')

    await userEvent.type(await screen.findByLabelText('Work done today'), 'Root canal, session 1 of 2')
    await userEvent.type(screen.getByLabelText('Total cost'), '1500')
    expect(screen.getByTestId('remaining')).toHaveTextContent('EGP 1,500.00')
    await userEvent.type(screen.getByLabelText('Amount paid now'), '1000')

    const remaining = screen.getByTestId('remaining')
    expect(remaining).toHaveTextContent('EGP 500.00')
    expect(remaining.className).toContain('text-red-600')

    await userEvent.selectOptions(screen.getByLabelText('Payment method'), 'Card')
    await userEvent.click(screen.getByRole('button', { name: 'Save visit' }))

    expect(create).toHaveBeenCalledWith({
      patient_id: 7,
      appointment_id: 40,
      work_done: 'Root canal, session 1 of 2',
      total_amount: '1500',
      paid_now: '1000',
      method: 'card',
    })
    expect(await screen.findByText('Visit saved. Remaining: EGP 500.00')).toBeInTheDocument()
    await expectPath('/doctor/patients/7')
  })

  it('after saving, the patient page offers a prescription for this visit', async () => {
    fakeServer()
    const saved = {
      id: 88,
      patient_id: 7,
      appointment_id: 40,
      visit_date: '2026-10-05',
      work_done: 'Extraction',
      total_amount: '800.00',
      paid: '800.00',
      remaining: '0.00',
      payment_status: 'paid',
      payments: [],
    }
    patientsApi.getPatient.mockResolvedValue({ ...MONA, address: 'Cairo', history: [], visits: [saved], outstanding_balance: '0.00' })
    renderForm('/doctor/visits/new?appointment=40')

    await userEvent.type(await screen.findByLabelText('Work done today'), 'Extraction')
    await userEvent.type(screen.getByLabelText('Total cost'), '800')
    await userEvent.type(screen.getByLabelText('Amount paid now'), '800')
    await userEvent.click(screen.getByRole('button', { name: 'Save visit' }))

    await expectPath('/doctor/patients/7')
    expect(await screen.findByText('The visit of 5 Oct 2026 is saved.')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Write prescription for this visit' })).toHaveAttribute(
      'href',
      '/doctor/patients/7/prescriptions/new?visit=88',
    )

    await userEvent.click(screen.getByRole('button', { name: 'Not now' }))
    expect(screen.queryByRole('link', { name: 'Write prescription for this visit' })).not.toBeInTheDocument()
  })

  it('shows 0 in black when fully paid', async () => {
    fakeServer()
    renderForm('/doctor/visits/new?appointment=40')

    await userEvent.type(await screen.findByLabelText('Total cost'), '300.50')
    await userEvent.type(screen.getByLabelText('Amount paid now'), '300.5')

    expect(screen.getByTestId('remaining')).toHaveTextContent('EGP 0.00')
    expect(screen.getByTestId('remaining').className).not.toContain('text-red-600')
  })

  it('blocks saving when paid is more than the total', async () => {
    const create = fakeServer()
    renderForm('/doctor/visits/new?appointment=40')

    await userEvent.type(await screen.findByLabelText('Work done today'), 'Cleaning')
    await userEvent.type(screen.getByLabelText('Total cost'), '500')
    await userEvent.type(screen.getByLabelText('Amount paid now'), '600')

    expect(screen.getByText('The amount paid cannot be more than the total.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Save visit' })).toBeDisabled()
    expect(create).not.toHaveBeenCalled()
  })

  it('keeps money inputs to digits with two decimals', async () => {
    fakeServer()
    renderForm('/doctor/visits/new?appointment=40')

    const total = await screen.findByLabelText('Total cost')
    await userEvent.type(total, '12a.345')

    expect(total).toHaveValue('12.34')
  })

  it('opens a walk-in visit for ?patient=', async () => {
    const create = fakeServer()
    renderForm('/doctor/visits/new?patient=7')

    expect(await screen.findByText('Walk-in visit')).toBeInTheDocument()
    await userEvent.type(screen.getByLabelText('Work done today'), 'Check-up')
    await userEvent.type(screen.getByLabelText('Total cost'), '200')
    await userEvent.click(screen.getByRole('button', { name: 'Save visit' }))

    expect(create).toHaveBeenCalledWith(expect.objectContaining({ patient_id: 7, appointment_id: null, paid_now: '0' }))
  })

  it('shows server validation errors on the fields', async () => {
    const create = fakeServer()
    create.mockRejectedValue(
      new AxiosError('x', 'ERR', undefined, undefined, {
        status: 422,
        data: { message: 'Invalid', errors: { appointment_id: ['x'], total_amount: ['The total cost field must be at least 0.'] } },
      }),
    )
    renderForm('/doctor/visits/new?appointment=40')

    await userEvent.type(await screen.findByLabelText('Work done today'), 'x')
    await userEvent.type(screen.getByLabelText('Total cost'), '1')
    await userEvent.click(screen.getByRole('button', { name: 'Save visit' }))

    expect(await screen.findByText('The total cost field must be at least 0.')).toBeInTheDocument()
  })

  it('asks to start from the schedule without a patient or appointment', async () => {
    fakeServer()
    renderForm('/doctor/visits/new')

    expect(await screen.findByText('Open a visit from the schedule or from a patient’s page.')).toBeInTheDocument()
  })
})
