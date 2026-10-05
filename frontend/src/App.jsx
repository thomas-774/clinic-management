import { useQuery } from '@tanstack/react-query'
import { getHealth } from './api/health'

export default function App() {
  const { data, isPending, isError, refetch } = useQuery({
    queryKey: ['health'],
    queryFn: getHealth,
  })

  return (
    <main className="min-h-screen bg-slate-50 flex items-center justify-center p-4">
      <div className="w-full max-w-sm rounded-xl bg-white p-6 shadow">
        <h1 className="text-xl font-bold text-slate-900">Clinic Management</h1>
        <p className="mt-4 text-sm text-slate-600">API status</p>
        {isPending && <p className="mt-1 text-slate-500">Checking…</p>}
        {isError && (
          <>
            <p className="mt-1 font-semibold text-red-600">Cannot reach the API</p>
            <button
              type="button"
              onClick={() => refetch()}
              className="mt-3 rounded bg-slate-900 px-3 py-1 text-sm text-white"
            >
              Retry
            </button>
          </>
        )}
        {data && (
          <>
            <p className="mt-1 text-lg font-semibold text-green-600">{data.status}</p>
            <p className="text-sm text-slate-500">Server time: {data.time}</p>
          </>
        )}
      </div>
    </main>
  )
}
