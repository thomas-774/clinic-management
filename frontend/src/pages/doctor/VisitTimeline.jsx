import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import PaymentStatusBadge from '../../components/PaymentStatusBadge'
import { formatDate, formatMoney, formatTime } from '../../utils/format'

const smallButton = 'rounded-md px-2 py-1 text-xs font-semibold hover:bg-slate-100'

function Amount({ label, children, className = 'text-slate-900' }) {
  return (
    <div>
      <dt className="text-xs text-slate-500">{label}</dt>
      <dd className={`font-semibold ${className}`}>{children}</dd>
    </div>
  )
}

function VisitItem({ visit, onAddPayment, onEdit }) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const owes = Number(visit.remaining) > 0
  const paymentsId = `visit-${visit.id}-payments`

  return (
    <li aria-label={formatDate(visit.visit_date)} className="relative ps-6">
      <span aria-hidden="true" className={`absolute start-0 top-1.5 h-3 w-3 rounded-full ${owes ? 'bg-red-500' : 'bg-green-500'}`} />
      <div className="flex flex-wrap items-center gap-2">
        <h3 className="font-semibold text-slate-900">{formatDate(visit.visit_date)}</h3>
        <PaymentStatusBadge status={visit.payment_status} />
        {owes && <span className="rounded-full bg-red-600 px-2 py-0.5 text-xs font-semibold text-white">{t('visits.unpaidBalance')}</span>}
        <div className="ms-auto flex gap-1">
          <button type="button" onClick={() => onEdit(visit)} className={`${smallButton} text-sky-700`}>
            {t('visits.edit')}
          </button>
          {owes && (
            <button type="button" onClick={() => onAddPayment(visit)} className={`${smallButton} text-green-700`}>
              {t('visits.addPayment')}
            </button>
          )}
        </div>
      </div>
      <p className="mt-1 whitespace-pre-line text-slate-700">{visit.work_done}</p>
      <dl className="mt-2 grid grid-cols-3 gap-2 text-sm">
        <Amount label={t('visits.total')}>{formatMoney(visit.total_amount)}</Amount>
        <Amount label={t('visits.paid')}>{formatMoney(visit.paid)}</Amount>
        <Amount label={t('visits.remaining')} className={owes ? 'text-red-600' : 'text-slate-900'}>
          {formatMoney(visit.remaining)}
        </Amount>
      </dl>
      {visit.payments.length > 0 && (
        <>
          <button
            type="button"
            aria-expanded={open}
            aria-controls={paymentsId}
            onClick={() => setOpen(!open)}
            className="mt-2 text-xs font-semibold text-sky-700 hover:underline"
          >
            {t(open ? 'visits.hidePayments' : 'visits.showPayments', { count: visit.payments.length })}
          </button>
          {open && (
            <ul id={paymentsId} className="mt-1 divide-y divide-slate-100 rounded-lg bg-slate-50 text-sm">
              {visit.payments.map((payment) => (
                <li key={payment.id} className="flex flex-wrap justify-between gap-2 px-3 py-1.5">
                  <span>
                    {formatDate(payment.paid_at)} · <span dir="ltr">{formatTime(payment.paid_at)}</span> · {t(`paymentMethod.${payment.method}`)}
                  </span>
                  <span className="font-semibold">{formatMoney(payment.amount)}</span>
                </li>
              ))}
            </ul>
          )}
        </>
      )}
    </li>
  )
}

/** Every visit, newest first, with its money (FR-C.5, FR-D.5). */
export default function VisitTimeline({ visits, onAddPayment, onEdit }) {
  const { t } = useTranslation()
  if (!visits.length) return <p className="text-sm text-slate-500">{t('patientDetails.noVisits')}</p>

  return (
    <ol aria-label={t('patientDetails.visits')} className="space-y-5 border-s-2 border-slate-100 ps-3">
      {visits.map((visit) => (
        <VisitItem key={visit.id} visit={visit} onAddPayment={onAddPayment} onEdit={onEdit} />
      ))}
    </ol>
  )
}
