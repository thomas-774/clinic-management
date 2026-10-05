import { fireEvent, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { AxiosError } from 'axios'
import { vi } from 'vitest'
import * as appointmentsApi from '../../api/appointments'
import * as authApi from '../../api/auth'
import { tokenStorage } from '../../api/client'
import * as slotsApi from '../../api/slots'
import { expectPath, renderAppAt } from '../../test/renderApp'

const slot = (date, time, end) => ({ start_at: `${date}T${time}:00+03:00`, end_at: `${date}T${end}:00+03:00` })

let freeSlots
function fakeSlots() {
  freeSlots = {
    '2026-10-05': [slot('2026-10-05', '17:00', '17:45'), slot('2026-10-05', '17:45', '18:30')],
    '2026-10-06': [slot('2026-10-06', '18:30', '19:15')],
  }
  return vi.spyOn(slotsApi, 'getSlots').mockImplementation(async (date) => ({
    data: freeSlots[date] ?? [],
    meta: { date, duration: 45, booking_window_days: 14 },
  }))
}

function apiError(status, message) {
  return new AxiosError(message, 'ERR', undefined, undefined, { status, data: { message } })
}

function renderBook() {
  tokenStorage.set('t')
  vi.spyOn(authApi, 'me').mockResolvedValue({ id: 5, name: 'Mona Ali', role: 'patient', patient_id: 1 })
  return renderAppAt('/patient/book')
}

describe('BookAppointment', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.restoreAllMocks()
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-10-05T05:00:00Z')) // 08:00 in Cairo
  })

  afterEach(() => vi.useRealTimers())

  it('limits the date picker to today … today + booking window', async () => {
    fakeSlots()
    renderBook()

    await screen.findByRole('button', { name: '17:00' })
    const date = screen.getByLabelText('Date')
    expect(date).toHaveValue('2026-10-05')
    expect(date).toHaveAttribute('min', '2026-10-05')
    expect(date).toHaveAttribute('max', '2026-10-19')
    expect(screen.getByText('You can book up to 14 days ahead.')).toBeInTheDocument()
  })

  it('books a slot from start to finish and lands on My appointments', async () => {
    const getSlots = fakeSlots()
    const book = vi.spyOn(appointmentsApi, 'bookAppointment').mockResolvedValue({ id: 9 })
    renderBook()

    await screen.findByRole('button', { name: '17:00' })
    fireEvent.change(screen.getByLabelText('Date'), { target: { value: '2026-10-06' } })
    await userEvent.click(await screen.findByRole('button', { name: '18:30' }))
    expect(getSlots).toHaveBeenCalledWith('2026-10-06')

    const dialog = screen.getByRole('dialog', { name: 'Confirm your appointment' })
    expect(dialog).toHaveTextContent('6 Oct 2026')
    expect(dialog).toHaveTextContent('18:30 – 19:15')
    expect(dialog).toHaveTextContent('45 minutes')

    await userEvent.click(within(dialog).getByRole('button', { name: 'Book this time' }))

    expect(book).toHaveBeenCalledWith('2026-10-06T18:30:00+03:00')
    await expectPath('/patient/appointments')
    expect(await screen.findByText('Your appointment is booked.')).toBeInTheDocument()
  })

  it('refreshes the grid with a toast when the slot was just taken (409)', async () => {
    const getSlots = fakeSlots()
    vi.spyOn(appointmentsApi, 'bookAppointment').mockImplementation(async () => {
      freeSlots['2026-10-05'] = freeSlots['2026-10-05'].slice(1) // someone took 17:00
      throw apiError(409, 'Slot no longer available.')
    })
    renderBook()

    await userEvent.click(await screen.findByRole('button', { name: '17:00' }))
    await userEvent.click(screen.getByRole('button', { name: 'Book this time' }))

    expect(await screen.findByText('That time was just taken. Please pick another one.')).toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    await vi.waitFor(() => expect(screen.queryByRole('button', { name: '17:00' })).not.toBeInTheDocument())
    expect(screen.getByRole('button', { name: '17:45' })).toBeInTheDocument()
    expect(getSlots.mock.calls.filter(([d]) => d === '2026-10-05').length).toBe(2)
    expect(screen.getByTestId('path')).toHaveTextContent('/patient/book')
  })

  it('explains the one-appointment rule with a link to My appointments (422)', async () => {
    fakeSlots()
    vi.spyOn(appointmentsApi, 'bookAppointment').mockRejectedValue(apiError(422, 'You already have an upcoming appointment.'))
    renderBook()

    await userEvent.click(await screen.findByRole('button', { name: '17:45' }))
    await userEvent.click(screen.getByRole('button', { name: 'Book this time' }))

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('You already have an upcoming appointment.')
    await userEvent.click(within(alert).getByRole('link', { name: 'Go to My appointments' }))
    await expectPath('/patient/appointments')
  })

  it('shows the empty state on a day without slots', async () => {
    fakeSlots()
    renderBook()

    await screen.findByRole('button', { name: '17:00' })
    fireEvent.change(screen.getByLabelText('Date'), { target: { value: '2026-10-09' } })

    expect(await screen.findByText('No free slots on this day.')).toBeInTheDocument()
  })
})
