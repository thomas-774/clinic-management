import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import Card from '../../components/Card'
import PaymentStatusBadge from '../../components/PaymentStatusBadge'
import { LoadError, Loading } from '../../components/QueryState'
import { useUnpaidVisits } from '../../hooks/useAssistant'
import { formatMoney, todayInClinic } from '../../utils/format'
import AddPaymentModal from '../doctor/AddPaymentModal'
import DayView from '../doctor/schedule/DayView'

/** Today's visits that still owe money, each with Record payment (FR-I.4). */
function WaitingToPay() {
  const { t } = useTranslation()
  const { data, isPending, isError, refetch } = useUnpaidVisits()
  const [paying, setPaying] = useState(null) // the visit being paid

  if (isPending) return <Loading />
  if (isError) return <LoadError onRetry={refetch} />
  if (data.length === 0) return <p className="py-4 text-center text-slate-500">{t('desk.noneWaiting')}</p>

  return (
    <>
      <ul aria-label={t('desk.waitingList')} className="divide-y divide-slate-100">
        {data.map((visit) => (
          <li key={visit.id} className="flex flex-col gap-3 py-3 sm:flex-row sm:items-center">
            <div className="min-w-0 flex-1">
              <Link to={`/assistant/patients/${visit.patient.id}`} className="font-semibold text-sky-800 hover:underline">
                {visit.patient.name}
              </Link>
              <p className="text-sm text-slate-500">
                {t('desk.total', { amount: formatMoney(visit.total_amount) })} · {t('desk.paid', { amount: formatMoney(visit.paid) })}
              </p>
            </div>
            <div className="flex items-center gap-3">
              <PaymentStatusBadge status={visit.payment_status} />
              <span className="text-end">
                <span className="block text-xs text-slate-500">{t('desk.remaining')}</span>
                <span className="font-bold text-slate-900">{formatMoney(visit.remaining)}</span>
              </span>
              <button
                type="button"
                onClick={() => setPaying(visit)}
                className="rounded-lg bg-sky-700 px-3 py-1.5 text-sm font-semibold text-white hover:bg-sky-800"
              >
                {t('desk.recordPayment')}
              </button>
            </div>
          </li>
        ))}
      </ul>

      {paying && (
        <AddPaymentModal
          visit={paying}
          title={t('desk.recordPaymentTitle', { name: paying.patient.name })}
          onClose={() => setPaying(null)}
        />
      )}
    </>
  )
}

/**
 * The assistant's home (FR-I.4, FR-I.6): today's queue with Arrived /
 * No-show / Cancel, and the visits waiting to pay.
 */
export default function Today() {
  const { t } = useTranslation()

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-slate-900">{t('pages.today')}</h1>

      <div className="grid items-start gap-6 xl:grid-cols-2">
        <section className="space-y-3">
          <div className="flex items-center justify-between gap-3">
            <h2 className="text-lg font-semibold text-slate-900">{t('desk.queue')}</h2>
            <Link to="/assistant/schedule" className="text-sm font-semibold text-sky-700 hover:underline">
              {t('desk.openSchedule')}
            </Link>
          </div>
          <DayView date={todayInClinic()} />
        </section>

        <Card title={t('desk.waitingToPay')}>
          <p className="mb-2 text-sm text-slate-500">{t('desk.waitingHint')}</p>
          <WaitingToPay />
        </Card>
      </div>
    </div>
  )
}
