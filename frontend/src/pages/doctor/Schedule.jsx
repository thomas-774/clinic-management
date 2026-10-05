import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useSearchParams } from 'react-router-dom'
import ConfirmDialog from '../../components/ConfirmDialog'
import { LoadError, Loading } from '../../components/QueryState'
import StatusBadge from '../../components/StatusBadge'
import { useSchedule, useUpdateAppointmentStatus } from '../../hooks/useSchedule'
import { useToast } from '../../toast/useToast'
import { errorMessage } from '../../utils/apiErrors'
import { addDays, dayOfWeek } from '../../utils/dates'
import { formatDate, formatTime, todayInClinic } from '../../utils/format'
import BookForPatientModal from './schedule/BookForPatientModal'

const navButton = 'rounded-lg px-3 py-1.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50'

/** No-show and Cancel ask first; Arrived is applied at once. */
const NEEDS_CONFIRM = ['no_show', 'cancelled']

function Actions({ appointment, onChange, busy }) {
  const { t } = useTranslation()
  if (appointment.status !== 'booked') return null
  return (
    <div className="flex flex-wrap gap-2">
      <button
        type="button"
        disabled={busy}
        onClick={() => onChange(appointment, 'checked_in')}
        className="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
      >
        {t('schedule.arrived')}
      </button>
      <button
        type="button"
        disabled={busy}
        onClick={() => onChange(appointment, 'no_show')}
        className="rounded-lg px-3 py-1.5 text-sm font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-50 disabled:opacity-60"
      >
        {t('schedule.noShow')}
      </button>
      <button
        type="button"
        disabled={busy}
        onClick={() => onChange(appointment, 'cancelled')}
        className="rounded-lg px-3 py-1.5 text-sm text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50 disabled:opacity-60"
      >
        {t('schedule.cancel')}
      </button>
    </div>
  )
}

/**
 * The doctor's day (FR-F.1, F.3, F.4): appointments by time with status
 * actions. The day is kept in ?date= so it survives reloads and can be linked.
 */
export default function Schedule() {
  const { t } = useTranslation()
  const toast = useToast()
  const [params, setParams] = useSearchParams()
  const today = todayInClinic()
  const date = params.get('date') ?? today
  const { data, isPending, isError, refetch } = useSchedule({ from: date, to: date })
  const updateStatus = useUpdateAppointmentStatus()
  const [pending, setPending] = useState(null) // { appointment, status } waiting for confirmation
  const [booking, setBooking] = useState(false)

  const goTo = (next) => setParams(next === today ? {} : { date: next })

  function apply(appointment, status) {
    updateStatus.mutate(
      { id: appointment.id, status },
      {
        onSuccess: () => toast.success(t(`schedule.done.${status}`, { name: appointment.patient.name })),
        onError: (error) => toast.error(errorMessage(error, t('common.networkError'))),
        onSettled: () => setPending(null),
      },
    )
  }

  function change(appointment, status) {
    if (NEEDS_CONFIRM.includes(status)) setPending({ appointment, status })
    else apply(appointment, status)
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-3">
        <h1 className="text-2xl font-bold text-slate-900">{t('pages.schedule')}</h1>
        <button
          type="button"
          onClick={() => setBooking(true)}
          className="ms-auto rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800"
        >
          {t('schedule.bookForPatient')}
        </button>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <button type="button" onClick={() => goTo(addDays(date, -1))} className={navButton} aria-label={t('schedule.previousDay')}>
          <span aria-hidden="true" className="rtl:inline-block rtl:rotate-180">
            ‹
          </span>
        </button>
        <h2 className="min-w-48 text-center text-lg font-semibold text-slate-900">
          {t(`days.${dayOfWeek(date)}`)} · {formatDate(date)}
        </h2>
        <button type="button" onClick={() => goTo(addDays(date, 1))} className={navButton} aria-label={t('schedule.nextDay')}>
          <span aria-hidden="true" className="rtl:inline-block rtl:rotate-180">
            ›
          </span>
        </button>
        {date !== today && (
          <button type="button" onClick={() => goTo(today)} className={navButton}>
            {t('schedule.today')}
          </button>
        )}
      </div>

      {isPending ? (
        <Loading />
      ) : isError ? (
        <LoadError onRetry={refetch} />
      ) : data.length === 0 ? (
        <p className="rounded-2xl bg-white p-6 text-center text-slate-500 ring-1 ring-slate-200">{t('schedule.emptyDay')}</p>
      ) : (
        <ul aria-label={t('schedule.appointments')} className="divide-y divide-slate-100 rounded-2xl bg-white ring-1 ring-slate-200">
          {data.map((appointment) => (
            <li
              key={appointment.id}
              className={`flex flex-col gap-3 p-4 sm:flex-row sm:items-center ${appointment.status === 'cancelled' ? 'opacity-60' : ''}`}
            >
              <span dir="ltr" className="w-28 shrink-0 font-semibold text-slate-900">
                {formatTime(appointment.start_at)} – {formatTime(appointment.end_at)}
              </span>
              <div className="min-w-0 flex-1">
                <p className="font-semibold text-slate-900">{appointment.patient.name}</p>
                <p dir="ltr" className="text-sm text-slate-500 rtl:text-end">
                  {appointment.patient.phone}
                </p>
              </div>
              <StatusBadge status={appointment.status} />
              <Actions appointment={appointment} onChange={change} busy={updateStatus.isPending} />
            </li>
          ))}
        </ul>
      )}

      <ConfirmDialog
        open={Boolean(pending)}
        title={pending ? t(`schedule.confirm.${pending.status}.title`) : ''}
        message={
          pending
            ? t(`schedule.confirm.${pending.status}.message`, {
                name: pending.appointment.patient.name,
                time: formatTime(pending.appointment.start_at),
              })
            : ''
        }
        confirmLabel={pending ? t(`schedule.confirm.${pending.status}.action`) : ''}
        onConfirm={() => apply(pending.appointment, pending.status)}
        onCancel={() => setPending(null)}
        busy={updateStatus.isPending}
      />

      {booking && <BookForPatientModal initialDate={date < today ? today : date} onClose={() => setBooking(false)} />}
    </div>
  )
}
