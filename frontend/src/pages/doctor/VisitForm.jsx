import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import Card from '../../components/Card'
import MoneyField from '../../components/MoneyField'
import { LoadError, Loading } from '../../components/QueryState'
import SelectField from '../../components/form/SelectField'
import TextAreaField from '../../components/form/TextAreaField'
import { usePatient } from '../../hooks/usePatient'
import { useSchedule } from '../../hooks/useSchedule'
import { useCreateVisit } from '../../hooks/useVisits'
import { useToast } from '../../toast/useToast'
import { errorMessage, fieldErrors } from '../../utils/apiErrors'
import { formatDate, formatMoney, formatTime, todayInClinic } from '../../utils/format'
import { PAYMENT_METHODS, fromPiastres, toPiastres } from '../../utils/money'

/**
 * Who the visit is for: the appointment handed over by the schedule (or found
 * in today's schedule after a reload), or the patient of a walk-in visit.
 */
function useVisitContext() {
  const [params] = useSearchParams()
  const { state } = useLocation()
  const appointmentId = Number(params.get('appointment')) || null
  const walkInPatientId = Number(params.get('patient')) || null
  const handed = state?.appointment?.id === appointmentId ? state.appointment : null
  const today = todayInClinic()

  const schedule = useSchedule({ from: today, to: today }, { enabled: Boolean(appointmentId && !handed) })
  const appointment = handed ?? schedule.data?.find((a) => a.id === appointmentId) ?? null
  const walkIn = usePatient(walkInPatientId, { enabled: Boolean(!appointmentId && walkInPatientId) })

  if (appointmentId) {
    return {
      appointment,
      patient: appointment?.patient ?? null,
      isPending: !handed && schedule.isPending,
      isError: !handed && schedule.isError,
      refetch: schedule.refetch,
    }
  }
  return {
    appointment: null,
    patient: walkIn.data ?? null,
    isPending: Boolean(walkInPatientId) && walkIn.isPending,
    isError: walkIn.isError,
    refetch: walkIn.refetch,
  }
}

/** Today's visit (FR-D.1 – D.4): work done, total, paid now and a live remaining. */
export default function VisitForm() {
  const { t } = useTranslation()
  const toast = useToast()
  const navigate = useNavigate()
  const { appointment, patient, isPending, isError, refetch } = useVisitContext()
  const create = useCreateVisit()
  const [form, setForm] = useState({ work_done: '', total_amount: '', paid_now: '', method: 'cash' })

  if (isPending) return <Loading />
  if (isError) return <LoadError onRetry={refetch} />
  if (!patient) {
    return (
      <div className="space-y-3">
        <h1 className="text-2xl font-bold text-slate-900">{t('pages.visitForm')}</h1>
        <p className="rounded-xl bg-amber-50 p-4 text-amber-900">{t('visitForm.noContext')}</p>
        <Link to="/doctor/schedule" className="inline-block text-sm font-semibold text-sky-700 hover:underline">
          {t('visitForm.toSchedule')}
        </Link>
      </div>
    )
  }

  const set = (field) => (value) => setForm((current) => ({ ...current, [field]: value }))

  // Live remaining in whole piastres; the server recomputes it on save (PR-1).
  const total = toPiastres(form.total_amount)
  const paid = form.paid_now === '' ? 0 : toPiastres(form.paid_now)
  const remaining = Number.isNaN(total) || Number.isNaN(paid) ? null : total - paid
  const overpaid = remaining !== null && remaining < 0

  const serverErrors = fieldErrors(create.error)
  const formError = create.error && !Object.keys(serverErrors).length ? errorMessage(create.error, t('common.networkError')) : ''
  const canSave = form.work_done.trim() !== '' && !Number.isNaN(total) && !Number.isNaN(paid) && !overpaid && !create.isPending

  function submit(event) {
    event.preventDefault()
    if (!canSave) return
    create.mutate(
      {
        patient_id: patient.id,
        appointment_id: appointment?.id ?? null,
        work_done: form.work_done.trim(),
        total_amount: form.total_amount,
        paid_now: form.paid_now === '' ? '0' : form.paid_now,
        method: form.method,
      },
      {
        onSuccess: (visit) => {
          toast.success(t('visitForm.saved', { remaining: formatMoney(visit.remaining) }))
          // The patient page then offers to write this visit's prescription (FR-J.7).
          navigate(`/doctor/patients/${patient.id}`, { state: { savedVisitId: visit.id } })
        },
      },
    )
  }

  return (
    <form onSubmit={submit} noValidate className="space-y-4">
      <div>
        <h1 className="text-2xl font-bold text-slate-900">{t('pages.visitForm')}</h1>
        <p className="mt-1 text-slate-700">
          <span className="font-semibold">{patient.name}</span>{' '}
          <span dir="ltr" className="text-sm text-slate-500">
            {patient.phone}
          </span>
        </p>
        <p className="text-sm text-slate-500">
          {appointment ? (
            <>
              {t('visitForm.appointment')}: {formatDate(appointment.start_at)} ·{' '}
              <span dir="ltr">
                {formatTime(appointment.start_at)} – {formatTime(appointment.end_at)}
              </span>
            </>
          ) : (
            t('visitForm.walkIn')
          )}
        </p>
      </div>

      {formError && (
        <p role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
          {formError}
        </p>
      )}

      <Card>
        <div className="space-y-4">
          <TextAreaField
            label={t('visitForm.workDone')}
            rows={4}
            value={form.work_done}
            onChange={(e) => set('work_done')(e.target.value)}
            error={serverErrors.work_done}
            required
          />
          <div className="grid gap-4 sm:grid-cols-2">
            <MoneyField label={t('visitForm.total')} value={form.total_amount} onChange={set('total_amount')} error={serverErrors.total_amount} required />
            <MoneyField
              label={t('visitForm.paidNow')}
              value={form.paid_now}
              onChange={set('paid_now')}
              error={overpaid ? t('visitForm.overpaid') : serverErrors.paid_now}
              hint={t('visitForm.paidNowHint')}
            />
            <SelectField
              label={t('visitForm.method')}
              value={form.method}
              onChange={(e) => set('method')(e.target.value)}
              options={PAYMENT_METHODS.map((m) => ({ value: m, label: t(`paymentMethod.${m}`) }))}
              error={serverErrors.method}
            />
            <div>
              <p id="remaining-label" className="block text-sm font-medium text-slate-700">
                {t('visitForm.remaining')}
              </p>
              <output
                aria-labelledby="remaining-label"
                aria-live="polite"
                data-testid="remaining"
                className={`mt-1 block rounded-lg bg-slate-50 px-3 py-2 text-lg font-bold ${remaining > 0 ? 'text-red-600' : 'text-slate-900'}`}
              >
                {remaining === null || overpaid ? '—' : formatMoney(fromPiastres(remaining))}
              </output>
            </div>
          </div>
        </div>
      </Card>

      <div className="flex justify-end gap-2">
        <button type="button" onClick={() => navigate(-1)} className="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">
          {t('common.cancel')}
        </button>
        <button
          type="submit"
          disabled={!canSave}
          className="rounded-lg bg-sky-700 px-5 py-2 text-sm font-semibold text-white hover:bg-sky-800 disabled:opacity-60"
        >
          {create.isPending ? t('common.saving') : t('visitForm.save')}
        </button>
      </div>
    </form>
  )
}
