/** One number on the dashboard: a label, the value and an optional line under it. */
export default function StatCard({ label, value, sub, tone = 'default' }) {
  const valueColor = tone === 'warning' ? 'text-amber-700' : 'text-slate-900'
  return (
    <div role="group" aria-label={label} className="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
      <p className="text-sm text-slate-600">{label}</p>
      <p dir="ltr" className={`mt-1 text-2xl font-bold break-words rtl:text-end ${valueColor}`}>
        {value}
      </p>
      {sub && <p className="mt-1 text-xs text-slate-500">{sub}</p>}
    </div>
  )
}
