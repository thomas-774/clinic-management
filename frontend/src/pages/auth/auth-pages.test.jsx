import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { AxiosError } from 'axios'
import { vi } from 'vitest'
import * as authApi from '../../api/auth'
import { tokenStorage } from '../../api/client'
import { expectPath, renderAppAt } from '../../test/renderApp'

function apiError(status, data) {
  return new AxiosError('error', String(status), {}, null, { status, data, headers: {}, config: {}, statusText: '' })
}

describe('Login page', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.restoreAllMocks()
  })

  it('logs the doctor in and opens the doctor area', async () => {
    const login = vi.spyOn(authApi, 'login').mockResolvedValue({ token: 'doc-token', user: { id: 1, role: 'doctor' } })
    renderAppAt('/login')

    await userEvent.type(screen.getByLabelText('Phone or email'), '01000000000')
    await userEvent.type(screen.getByLabelText('Password'), 'password')
    await userEvent.click(screen.getByRole('button', { name: 'Log in' }))

    await expectPath('/doctor')
    expect(login).toHaveBeenCalledWith({ login: '01000000000', password: 'password' })
    expect(tokenStorage.get()).toBe('doc-token')
  })

  it('returns a patient to the patient page they first asked for', async () => {
    vi.spyOn(authApi, 'login').mockResolvedValue({ token: 't', user: { id: 2, role: 'patient' } })
    renderAppAt('/patient/appointments')
    await expectPath('/login')

    await userEvent.type(screen.getByLabelText('Phone or email'), '01012345678')
    await userEvent.type(screen.getByLabelText('Password'), 'secret-pass')
    await userEvent.click(screen.getByRole('button', { name: 'Log in' }))

    await expectPath('/patient/appointments')
  })

  it('shows the server error under the field for wrong credentials', async () => {
    vi.spyOn(authApi, 'login').mockRejectedValue(
      apiError(422, { message: 'x', errors: { login: ['These credentials do not match our records.'] } }),
    )
    renderAppAt('/login')

    await userEvent.type(screen.getByLabelText('Phone or email'), '01000000000')
    await userEvent.type(screen.getByLabelText('Password'), 'wrong')
    await userEvent.click(screen.getByRole('button', { name: 'Log in' }))

    expect(await screen.findByText('These credentials do not match our records.')).toBeInTheDocument()
    expect(screen.getByLabelText('Phone or email')).toHaveAttribute('aria-invalid', 'true')
    await expectPath('/login')
  })

  it('shows the message of a non-field error such as the rate limit', async () => {
    vi.spyOn(authApi, 'login').mockRejectedValue(apiError(429, { message: 'Too many login attempts.' }))
    renderAppAt('/login')

    await userEvent.type(screen.getByLabelText('Phone or email'), '01000000000')
    await userEvent.type(screen.getByLabelText('Password'), 'x')
    await userEvent.click(screen.getByRole('button', { name: 'Log in' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Too many login attempts.')
  })
})

describe('Register page', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.restoreAllMocks()
  })

  async function fillForm() {
    await userEvent.type(screen.getByLabelText('Full name'), 'Mona Ali')
    await userEvent.type(screen.getByLabelText('Phone'), '01012345678')
    await userEvent.type(screen.getByLabelText('Address'), '12 Tahrir St')
    await userEvent.type(screen.getByLabelText('Password'), 'secret-pass')
    await userEvent.type(screen.getByLabelText('Confirm password'), 'secret-pass')
  }

  it('creates the account and opens the patient area', async () => {
    const register = vi
      .spyOn(authApi, 'register')
      .mockResolvedValue({ token: 'new-token', user: { id: 5, role: 'patient', patient_id: 3 } })
    renderAppAt('/register')

    await fillForm()
    await userEvent.click(screen.getByRole('button', { name: 'Create account' }))

    await expectPath('/patient')
    expect(register).toHaveBeenCalledWith({
      name: 'Mona Ali',
      phone: '01012345678',
      email: '',
      address: '12 Tahrir St',
      password: 'secret-pass',
      password_confirmation: 'secret-pass',
    })
    expect(tokenStorage.get()).toBe('new-token')
  })

  it('shows each 422 field error under its field', async () => {
    vi.spyOn(authApi, 'register').mockRejectedValue(
      apiError(422, {
        message: 'x',
        errors: { phone: ['The phone has already been taken.'], password: ['The password field confirmation does not match.'] },
      }),
    )
    renderAppAt('/register')

    await fillForm()
    await userEvent.click(screen.getByRole('button', { name: 'Create account' }))

    expect(await screen.findByText('The phone has already been taken.')).toBeInTheDocument()
    expect(screen.getByText('The password field confirmation does not match.')).toBeInTheDocument()
    expect(screen.getByLabelText('Phone')).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByLabelText('Full name')).not.toHaveAttribute('aria-invalid')
  })
})
