import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../../auth/useAuth'
import TextField from '../../components/form/TextField'
import AuthLayout from '../../layouts/AuthLayout'
import { errorMessage, fieldErrors } from '../../utils/apiErrors'

export default function Login() {
  const { login } = useAuth()
  const [form, setForm] = useState({ login: '', password: '' })
  const [errors, setErrors] = useState({})
  const [formError, setFormError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const update = (field) => (event) => setForm((current) => ({ ...current, [field]: event.target.value }))

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setErrors({})
    setFormError('')
    try {
      // GuestRoute moves the logged-in user on to their area.
      await login(form)
    } catch (error) {
      const fields = fieldErrors(error)
      setErrors(fields)
      if (!Object.keys(fields).length) {
        setFormError(errorMessage(error, 'Could not reach the server. Please try again.'))
      }
      setSubmitting(false)
    }
  }

  return (
    <AuthLayout
      title="Log in"
      footer={
        <>
          New patient?{' '}
          <Link to="/register" className="font-medium text-sky-700 hover:underline">
            Create an account
          </Link>
        </>
      }
    >
      <form onSubmit={handleSubmit} noValidate className="space-y-4">
        {formError && (
          <p role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
            {formError}
          </p>
        )}
        <TextField
          label="Phone or email"
          name="login"
          autoComplete="username"
          dir="ltr"
          value={form.login}
          onChange={update('login')}
          error={errors.login}
          required
        />
        <TextField
          label="Password"
          name="password"
          type="password"
          autoComplete="current-password"
          value={form.password}
          onChange={update('password')}
          error={errors.password}
          required
        />
        <button
          type="submit"
          disabled={submitting}
          className="w-full rounded-lg bg-sky-700 px-4 py-2.5 font-semibold text-white hover:bg-sky-800 disabled:opacity-60"
        >
          {submitting ? 'Logging in…' : 'Log in'}
        </button>
      </form>
    </AuthLayout>
  )
}
