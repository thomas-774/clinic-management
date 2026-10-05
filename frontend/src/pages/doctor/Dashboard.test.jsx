import { cleanup, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { AxiosError } from 'axios'
import { vi } from 'vitest'
import * as appointmentsApi from '../../api/appointments'
import * as authApi from '../../api/auth'
import { tokenStorage } from '../../api/client'
import * as reportsApi from '../../api/reports'
import { renderAppAt } from '../../test/renderApp'

const MONA = { id: 1, name: 'Mona Ali', phone: '01011112222' }
const AHMED = { id: 2, name: 'Ahmed Hassan', phone: '01233334444' }

const appt = (id, time, end, patient, status = 'booked') => ({
  id,
  start_at: `2026-10-05T${time}:00+03:00`,
  end_at: `2026-10-05T${end}:00+03:00`,
  status,
  checked_in_at: null,
  cancelled_at: null,
  patient,
})

/** Report numbers like the API; `pay()` records a payment today. */
function fakeReports() {
  const state = {
    day: { from: '2026-10-05', to: '2026-10-05', visits: 1, revenue: '1200.00' },
    week: { from: '2026-10-03', to: '2026-10-09', visits: 2, revenue: '1500.00' },
    month: { from: '2026-10-01', to: '2026-10-31', visits: 3, revenue: '2100.00' },
    outstanding: '1200.00',
  }
  const summary = vi
    .spyOn(reportsApi, 'getReportSummary')
    .mockImplementation(async (period) => ({ period, ...state[period], outstanding: state.outstanding }))
  const pay = (amount) => {
    for (const period of ['day', 'week', 'month']) state[period].revenue = (Number(state[period].revenue) + amount).toFixed(2)
    state.outstanding = (Number(state.outstanding) - amount).toFixed(2)
  }
  return { summary, pay }
}

function renderDashboard() {
  tokenStorage.set('t')
  vi.spyOn(authApi, 'me').mockResolvedValue({ id: 1, name: 'Dr. Doctor', role: 'doctor' })
  return renderAppAt('/doctor')
}

const group = (name) => screen.getByRole('heading', { name }).closest('section')
const stat = (container, label) => within(container).getByRole('group', { name: label })

describe('Dashboard', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.restoreAllMocks()
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-10-05T14:20:00Z')) // Monday 17:20 in Cairo
    vi.spyOn(appointmentsApi, 'getSchedule').mockResolvedValue([])
  })

  afterEach(() => vi.useRealTimers())

  it('shows the number of visits and revenue for today, this week and this month', async () => {
    const { summary } = fakeReports()
    renderDashboard()

    const today = await findGroup('Today')
    expect([...new Set(summary.mock.calls.map(([period]) => period))].sort()).toEqual(['day', 'month', 'week'])

    expect(stat(today, 'Visits')).toHaveTextContent('1')
    expect(stat(today, 'Revenue')).toHaveTextContent('EGP 1,200.00')
    expect(today).toHaveTextContent('5 Oct 2026')

    const week = group('This week')
    expect(stat(week, 'Visits')).toHaveTextContent('2')
    expect(stat(week, 'Revenue')).toHaveTextContent('EGP 1,500.00')
    expect(week).toHaveTextContent('3 Oct 2026 – 9 Oct 2026')

    const month = group('This month')
    expect(stat(month, 'Visits')).toHaveTextContent('3')
    expect(stat(month, 'Revenue')).toHaveTextContent('EGP 2,100.00')
  })

  it('shows outstanding balances on a card of their own, not inside revenue', async () => {
    fakeReports()
    renderDashboard()

    const card = (await screen.findByRole('heading', { name: 'Outstanding balances' })).closest('section')
    expect(await within(card).findByText('EGP 1,200.00')).toBeInTheDocument()
    expect(card).toHaveTextContent('Not included in revenue.')
    for (const name of ['Today', 'This week', 'This month']) {
      expect(within(group(name)).queryByText(/still owed/i)).not.toBeInTheDocument()
    }
  })

  it("lists today's queue with status and quick actions, without phone numbers", async () => {
    fakeReports()
    appointmentsApi.getSchedule.mockResolvedValue([appt(1, '17:00', '17:45', MONA), appt(2, '17:45', '18:30', AHMED, 'checked_in')])
    renderDashboard()

    const list = await screen.findByRole('list', { name: 'Appointments' })
    expect(appointmentsApi.getSchedule).toHaveBeenCalledWith({ from: '2026-10-05', to: '2026-10-05' })
    const rows = within(list).getAllByRole('listitem')
    expect(rows[0]).toHaveTextContent('17:00 – 17:45')
    expect(rows[0]).toHaveTextContent('Booked')
    expect(within(rows[0]).getByRole('button', { name: 'Arrived' })).toBeInTheDocument()
    expect(within(rows[1]).getByRole('link', { name: 'Start visit' })).toHaveAttribute('href', '/doctor/visits/new?appointment=2')
    expect(list).not.toHaveTextContent('01011112222')
    expect(screen.getByRole('link', { name: 'Open schedule' })).toHaveAttribute('href', '/doctor/schedule')
  })

  it('marks a patient as arrived from the queue', async () => {
    fakeReports()
    appointmentsApi.getSchedule.mockResolvedValue([appt(1, '17:00', '17:45', MONA)])
    const update = vi.spyOn(appointmentsApi, 'updateAppointmentStatus').mockResolvedValue({})
    renderDashboard()

    await userEvent.click(await screen.findByRole('button', { name: 'Arrived' }))

    expect(update).toHaveBeenCalledWith(1, 'checked_in')
  })

  it("shows the new revenue for today after a payment and a refresh", async () => {
    const reports = fakeReports()
    renderDashboard()
    expect(stat(await findGroup('Today'), 'Revenue')).toHaveTextContent('EGP 1,200.00')

    reports.pay(250)
    cleanup()
    renderDashboard()

    const today = await findGroup('Today')
    expect(await within(today).findByText('EGP 1,450.00')).toBeInTheDocument()
    expect(await screen.findByText('EGP 950.00')).toBeInTheDocument() // outstanding went down
  })

  it('offers a retry when the numbers cannot load', async () => {
    vi.spyOn(reportsApi, 'getReportSummary').mockRejectedValue(new AxiosError('Network Error'))
    renderDashboard()

    expect((await screen.findAllByRole('alert')).length).toBeGreaterThanOrEqual(3)
    expect(screen.getAllByRole('button', { name: 'Retry' }).length).toBeGreaterThanOrEqual(3)
  })
})

async function findGroup(name) {
  const heading = await screen.findByRole('heading', { name })
  const section = heading.closest('section')
  await within(section).findByText(/EGP/)
  return section
}
