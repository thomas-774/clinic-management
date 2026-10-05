import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router-dom'
import Card from '../../components/Card'
import Modal from '../../components/Modal'
import { LoadError } from '../../components/QueryState'
import SlotGrid from '../../components/SlotGrid'
import TextField from '../../components/form/TextField'
import { useBookAppointment } from '../../hooks/useAppointments'
import { useSlots } from '../../hooks/useSlots'
import { useToast } from '../../toast/useToast'
import { errorMessage } from '../../utils/apiErrors'
import { addDays } from '../../utils/dates'
import { formatDate, formatTime, todayInClinic } from '../../utils/format'

/** Until the first slot list arrives with the doctor's setting. */
const DEFAULT_WINDOW_DAYS = 30

/**
 * Date picker → free slots → confirm (FR-E.1 – E.3). The server checks the
 * slot again; a slot taken meanwhile comes back as 409 and the grid reloads.
 */
export default function BookAppointment() {
  const { t } = useTranslation()
  const toast = useToast()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const today = todayInClinic()
  const [date, setDate] = useState(today)
  const [slot, setSlot] = useState(null)
  const [limitMessage, setLimitMessage] = useState('')
  const slots = useSlots(date)
  const book = useBookAppointment()

  const windowDays = slots.data?.meta.booking_window_days ?? DEFAULT_WINDOW_DAYS
  const duration = slots.data?.meta.duration

  function changeDate(value) {
    setDate(value)
    setSlot(null)
  }

  function confirm() {
    book.mutate(slot.start_at, {
      onSuccess: () => {
        toast.success(t('book.booked'))
        navigate('/patient/appointments')
      },
      onError: (error) => {
        setSlot(null)
        const status = error?.response?.status
        if (status === 409) {
          toast.error(t('book.slotTaken'))
          queryClient.invalidateQueries({ queryKey: ['slots', date] })
        } else if (status === 422) {
          setLimitMessage(errorMessage(error, t('book.alreadyBooked')))
        } else {
          toast.error(errorMessage(error, t('common.networkError')))
        }
      },
    })
  }

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold text-slate-900">{t('pages.bookAppointment')}</h1>

      {limitMessage && (
        <div role="alert" className="rounded-xl bg-amber-50 p-4 text-amber-900 ring-1 ring-amber-200">
          <p>{limitMessage}</p>
          <Link to="/patient/appointments" className="mt-2 inline-block text-sm font-semibold text-sky-700 hover:underline">
            {t('book.goToMyAppointments')}
          </Link>
        </div>
      )}

      <Card>
        <TextField
          label={t('book.date')}
          type="date"
          dir="ltr"
          value={date}
          min={today}
          max={addDays(today, windowDays)}
          onChange={(e) => changeDate(e.target.value)}
          hint={t('book.dateHint', { count: windowDays })}
          className="max-w-xs"
        />
      </Card>

      <Card title={date ? t('book.freeTimesOn', { date: formatDate(date) }) : undefined}>
        {!date ? (
          <p className="text-slate-600">{t('book.pickDate')}</p>
        ) : slots.isError ? (
          <LoadError onRetry={slots.refetch} />
        ) : (
          <SlotGrid slots={slots.data?.data} loading={slots.isPending} selected={slot?.start_at} onSelect={setSlot} />
        )}
      </Card>

      <Modal open={Boolean(slot)} title={t('book.confirmTitle')} onClose={() => setSlot(null)}>
        {slot && (
          <>
            <dl className="space-y-2 text-slate-900">
              <div className="flex gap-3">
                <dt className="w-24 text-slate-500">{t('book.date')}</dt>
                <dd>{formatDate(slot.start_at)}</dd>
              </div>
              <div className="flex gap-3">
                <dt className="w-24 text-slate-500">{t('book.time')}</dt>
                <dd dir="ltr">
                  {formatTime(slot.start_at)} – {formatTime(slot.end_at)}
                </dd>
              </div>
              {duration && (
                <div className="flex gap-3">
                  <dt className="w-24 text-slate-500">{t('book.duration')}</dt>
                  <dd>{t('settings.minutes', { count: duration })}</dd>
                </div>
              )}
            </dl>
            <div className="mt-6 flex justify-end gap-2">
              <button type="button" onClick={() => setSlot(null)} className="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">
                {t('common.cancel')}
              </button>
              <button
                type="button"
                onClick={confirm}
                disabled={book.isPending}
                className="rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800 disabled:opacity-60"
              >
                {book.isPending ? t('book.booking') : t('book.confirm')}
              </button>
            </div>
          </>
        )}
      </Modal>
    </div>
  )
}
