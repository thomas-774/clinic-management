import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { vi } from 'vitest'
import Status from './Status'
import * as health from '../api/health'

function renderApp() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <Status />
    </QueryClientProvider>,
  )
}

describe('Status page', () => {
  it('shows the API status and server time from the health endpoint', async () => {
    vi.spyOn(health, 'getHealth').mockResolvedValue({ status: 'ok', time: '2026-10-05T12:00:00+03:00' })
    renderApp()
    expect(await screen.findByText('ok')).toBeInTheDocument()
    expect(screen.getByText(/2026-10-05T12:00:00\+03:00/)).toBeInTheDocument()
  })

  it('shows an error with a retry button when the API is down', async () => {
    vi.spyOn(health, 'getHealth').mockRejectedValue(new Error('network'))
    renderApp()
    expect(await screen.findByText('Cannot reach the API')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Retry' })).toBeInTheDocument()
  })
})
