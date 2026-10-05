import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import Card from '../../components/Card'
import HistoryList from '../../components/HistoryList'
import { LoadError, Loading } from '../../components/QueryState'
import StatusBadge from '../../components/StatusBadge'
import TextField from '../../components/form/TextField'
import { useProfile, useUpdateProfile } from '../../hooks/useProfile'
import { errorMessage, fieldErrors } from '../../utils/apiErrors'
import { formatDate, formatMoney, formatTime } from '../../utils/format'

function InfoRow({ label, children }) {
  return (
    <div className="flex flex-col gap-0.5 py-2 sm:flex-row sm:gap-4">
      <dt className="text-sm text-slate-500 sm:w-28 sm:shrink-0">{label}</dt>
      <dd className="text-slate-900">{children}</dd>
    </div>
  )
}

/** Phone and address are the only fields a patient may change (FR-B.5). */
function ContactForm({ profile, onDone }) {
  const { t } = useTranslation()
  const update = useUpdateProfile()
  const [form, setForm] = useState({ phone: profile.phone, address: profile.address })
  const errors = fieldErrors(update.error)
  const formError = update.error && !Object.keys(errors).length ? errorMessage(update.error, t('common.networkError')) : ''

  function handleSubmit(event) {
    event.preventDefault()
    update.mutate(form, { onSuccess: () => onDone(true) })
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="space-y-3">
      {formError && (
        <p role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
          {formError}
        </p>
      )}
      <TextField
        label={t('patientHome.phone')}
        type="tel"
        dir="ltr"
        value={form.phone}
        onChange={(e) => setForm({ ...form, phone: e.target.value })}
        error={errors.phone}
        required
      />
      <TextField
        label={t('patientHome.address')}
        value={form.address}
        onChange={(e) => setForm({ ...form, address: e.target.value })}
        error={errors.address}
        required
      />
      <div className="flex gap-2">
        <button
          type="submit"
          disabled={update.isPending}
          className="rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800 disabled:opacity-60"
        >
          {update.isPending ? t('common.saving') : t('common.save')}
        </button>
        <button type="button" onClick={() => onDone(false)} className="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">
          {t('common.cancel')}
        </button>
      </div>
    </form>
  )
}

export default function PatientHome() {
  const { t } = useTranslation()
  const { data: profile, isPending, isError, refetch } = useProfile()
  const [editing, setEditing] = useState(false)
  const [saved, setSaved] = useState(false)

  if (isPending) return <Loading />
  if (isError) return <LoadError onRetry={refetch} />

  const next = profile.next_appointment
  const hasBalance = Number(profile.outstanding_balance) > 0

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold text-slate-900">{t('patientHome.greeting', { name: profile.name })}</h1>

      <div className="grid gap-4 sm:grid-cols-2">
        <Card title={t('patientHome.nextAppointment')}>
          {next ? (
            <>
              <p className="font-semibold text-slate-900">{formatDate(next.start_at)}</p>
              <p className="mt-1 flex items-center gap-2 text-slate-700">
                <span dir="ltr">
                  {formatTime(next.start_at)} – {formatTime(next.end_at)}
                </span>
                <StatusBadge status={next.status} />
              </p>
              <Link to="/patient/appointments" className="mt-2 inline-block text-sm font-semibold text-sky-700 hover:underline">
                {t('patientHome.manageAppointment')}
              </Link>
            </>
          ) : (
            <>
              <p className="text-sm text-slate-500">{t('patientHome.noAppointment')}</p>
              <Link to="/patient/book" className="mt-2 inline-block text-sm font-semibold text-sky-700 hover:underline">
                {t('patientHome.bookNow')}
              </Link>
            </>
          )}
        </Card>
        <Card title={t('patientHome.balance')}>
          <p className={`text-2xl font-bold ${hasBalance ? 'text-red-600' : 'text-slate-900'}`}>
            {formatMoney(profile.outstanding_balance)}
          </p>
          {!hasBalance && <p className="text-sm text-slate-500">{t('patientHome.nothingDue')}</p>}
        </Card>
      </div>

      <Card
        title={t('patientHome.personalInfo')}
        action={
          !editing && (
            <button
              type="button"
              onClick={() => {
                setEditing(true)
                setSaved(false)
              }}
              className="text-sm font-semibold text-sky-700 hover:underline"
            >
              {t('patientHome.editContact')}
            </button>
          )
        }
      >
        {saved && (
          <p role="status" className="mb-3 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-700">
            {t('patientHome.contactSaved')}
          </p>
        )}
        {editing ? (
          <ContactForm
            profile={profile}
            onDone={(didSave) => {
              setEditing(false)
              setSaved(didSave)
            }}
          />
        ) : (
          <dl className="divide-y divide-slate-100">
            <InfoRow label={t('patientHome.name')}>{profile.name}</InfoRow>
            <InfoRow label={t('patientHome.phone')}>
              <span dir="ltr">{profile.phone}</span>
            </InfoRow>
            <InfoRow label={t('patientHome.address')}>{profile.address}</InfoRow>
          </dl>
        )}
      </Card>

      <Card title={t('patientHome.currentIllness')}>
        <p className={profile.current_illness ? 'text-slate-900' : 'text-sm text-slate-500'}>
          {profile.current_illness || t('patientHome.noIllness')}
        </p>
      </Card>

      <Card title={t('patientHome.history')}>
        <HistoryList entries={profile.simple_history} mode="simple" />
      </Card>
    </div>
  )
}
