import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { vi } from 'vitest'
import * as authApi from '../../api/auth'
import { tokenStorage } from '../../api/client'
import * as patientsApi from '../../api/doctorPatients'
import * as visitsApi from '../../api/visits'
import { renderAppAt } from '../../test/renderApp'
import { fromPiastres, toPiastres } from '../../utils/money'

/** In-memory visits: the server recomputes paid / remaining / status like PaymentService. */
function fakeServer() {
  let visits = [
    {
      id: 2,
      visit_date: '2026-10-01',
      work_done: 'Cleaning',
      total_amount: '300.00',
      payments: [{ id: 21, amount: '300.00', method: 'cash', paid_at: '2026-10-01T18:00:00+03:00' }],
    },
    {
      id: 1,
      visit_date: '2026-08-10',
      work_done: 'Root canal, session 1 of 2',
      total_amount: '1500.00',
      payments: [{ id: 11, amount: '1000.00', method: 'cash', paid_at: '2026-08-10T18:00:00+03:00' }],
    },
  ]
  let nextPaymentId = 100
  const withMoney = (v) => {
    const paid = v.payments.reduce((sum, p) => sum + toPiastres(p.amount), 0)
    const remaining = toPiastres(v.total_amount) - paid
    const payment_status = remaining <= 0 ? 'paid' : paid === 0 ? 'unpaid' : 'partially_paid'
    return { ...v, paid: fromPiastres(paid), remaining: fromPiastres(remaining), payment_status }
  }
  vi.spyOn(patientsApi, 'getPatient').mockImplementation(async () => {
    const shown = visits.map(withMoney)
    return {
      id: 7,
      name: 'Mona Ali',
      phone: '01011112222',
      email: null,
      address: 'Cairo',
      date_of_birth: null,
      gender: null,
      current_illness: null,
      history: [],
      visits: shown,
      outstanding_balance: fromPiastres(shown.reduce((sum, v) => sum + toPiastres(v.remaining), 0)),
    }
  })
  const addPayment = vi.spyOn(visitsApi, 'addPayment').mockImplementation(async (visitId, fields) => {
    visits = visits.map((v) =>
      v.id === visitId ? { ...v, payments: [...v.payments, { id: nextPaymentId++, paid_at: '2026-10-05T17:00:00+03:00', ...fields }] } : v,
    )
    return withMoney(visits.find((v) => v.id === visitId))
  })
  const updateVisit = vi.spyOn(visitsApi, 'updateVisit').mockImplementation(async (id, fields) => {
    visits = visits.map((v) => (v.id === id ? { ...v, ...fields } : v))
    return withMoney(visits.find((v) => v.id === id))
  })
  return { addPayment, updateVisit }
}

function renderDetails() {
  tokenStorage.set('t')
  vi.spyOn(authApi, 'me').mockResolvedValue({ id: 1, name: 'Dr. Doctor', role: 'doctor' })
  return renderAppAt('/doctor/patients/7')
}

const visitItem = (date) => within(screen.getByRole('list', { name: 'Visits' })).getByRole('listitem', { name: date })

describe('PatientDetails: visit timeline', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.restoreAllMocks()
  })

  it('shows each visit newest first with money, status and an unpaid flag', async () => {
    fakeServer()
    renderDetails()

    const list = await screen.findByRole('list', { name: 'Visits' })
    const items = within(list).getAllByRole('listitem').filter((li) => li.parentElement === list)
    expect(items.map((li) => li.getAttribute('aria-label'))).toEqual(['1 Oct 2026', '10 Aug 2026'])

    const old = visitItem('10 Aug 2026')
    expect(old).toHaveTextContent('Root canal, session 1 of 2')
    expect(old).toHaveTextContent('TotalEGP 1,500.00')
    expect(old).toHaveTextContent('PaidEGP 1,000.00')
    expect(old).toHaveTextContent('RemainingEGP 500.00')
    expect(within(old).getByText('Partially paid')).toBeInTheDocument()
    expect(within(old).getByText('Unpaid balance')).toBeInTheDocument()

    const paid = visitItem('1 Oct 2026')
    expect(within(paid).getByText('Paid', { selector: '[data-status]' })).toBeInTheDocument()
    expect(within(paid).queryByText('Unpaid balance')).not.toBeInTheDocument()
    expect(within(paid).queryByRole('button', { name: 'Add payment' })).not.toBeInTheDocument()

    expect(screen.getByTestId('outstanding')).toHaveTextContent('EGP 500.00')
  })

  it('expands the payments of a visit', async () => {
    fakeServer()
    renderDetails()

    await screen.findByRole('list', { name: 'Visits' })
    const toggle = within(visitItem('10 Aug 2026')).getByRole('button', { name: 'Show payment (1)' })
    expect(toggle).toHaveAttribute('aria-expanded', 'false')
    await userEvent.click(toggle)

    expect(toggle).toHaveAttribute('aria-expanded', 'true')
    expect(within(visitItem('10 Aug 2026')).getByText('EGP 1,000.00', { selector: 'li span' })).toBeInTheDocument()
    expect(visitItem('10 Aug 2026')).toHaveTextContent('10 Aug 2026 · 18:00 · Cash')
  })

  it('records an installment on an old visit and the balance updates', async () => {
    const server = fakeServer()
    renderDetails()

    await screen.findByRole('list', { name: 'Visits' })
    await userEvent.click(within(visitItem('10 Aug 2026')).getByRole('button', { name: 'Add payment' }))

    const dialog = screen.getByRole('dialog', { name: 'Add a payment for 10 Aug 2026' })
    const amount = within(dialog).getByLabelText('Amount')
    expect(amount).toHaveValue('500.00') // pre-filled with the remaining
    await userEvent.clear(amount)
    await userEvent.type(amount, '200')
    await userEvent.selectOptions(within(dialog).getByLabelText('Payment method'), 'Wallet')
    await userEvent.click(within(dialog).getByRole('button', { name: 'Save payment' }))

    expect(server.addPayment).toHaveBeenCalledWith(1, { amount: '200', method: 'wallet' })
    expect(await screen.findByText('Payment recorded. Remaining: EGP 300.00')).toBeInTheDocument()
    await vi.waitFor(() => expect(screen.getByTestId('outstanding')).toHaveTextContent('EGP 300.00'))
    expect(visitItem('10 Aug 2026')).toHaveTextContent('RemainingEGP 300.00')
  })

  it('will not pay more than the remaining amount', async () => {
    const server = fakeServer()
    renderDetails()

    await screen.findByRole('list', { name: 'Visits' })
    await userEvent.click(within(visitItem('10 Aug 2026')).getByRole('button', { name: 'Add payment' }))
    const dialog = screen.getByRole('dialog')
    await userEvent.clear(within(dialog).getByLabelText('Amount'))
    await userEvent.type(within(dialog).getByLabelText('Amount'), '600')

    expect(within(dialog).getByText('The amount cannot be more than the remaining EGP 500.00.')).toBeInTheDocument()
    expect(within(dialog).getByRole('button', { name: 'Save payment' })).toBeDisabled()
    expect(server.addPayment).not.toHaveBeenCalled()
  })

  it('edits work done and total; the total cannot go below what was paid', async () => {
    const server = fakeServer()
    renderDetails()

    await screen.findByRole('list', { name: 'Visits' })
    await userEvent.click(within(visitItem('10 Aug 2026')).getByRole('button', { name: 'Edit' }))
    const dialog = screen.getByRole('dialog', { name: 'Edit visit of 10 Aug 2026' })
    const total = within(dialog).getByLabelText('Total cost')

    await userEvent.clear(total)
    await userEvent.type(total, '900')
    expect(within(dialog).getByText('The total cannot be lower than the EGP 1,000.00 already paid.')).toBeInTheDocument()
    expect(within(dialog).getByRole('button', { name: 'Save' })).toBeDisabled()

    await userEvent.clear(total)
    await userEvent.type(total, '1800')
    await userEvent.clear(within(dialog).getByLabelText('Work done today'))
    await userEvent.type(within(dialog).getByLabelText('Work done today'), 'Root canal, both sessions')
    await userEvent.click(within(dialog).getByRole('button', { name: 'Save' }))

    expect(server.updateVisit).toHaveBeenCalledWith(1, { work_done: 'Root canal, both sessions', total_amount: '1800' })
    expect(await screen.findByText('Root canal, both sessions')).toBeInTheDocument()
    expect(screen.getByTestId('outstanding')).toHaveTextContent('EGP 800.00')
  })
})
