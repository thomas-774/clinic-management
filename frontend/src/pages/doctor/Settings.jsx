import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { LoadError, Loading } from '../../components/QueryState'
import { useDoctorSettings, useUpdateSettings, useUpdateWorkingHours, useWorkingHours } from '../../hooks/useSettings'
import { useToast } from '../../toast/useToast'
import { errorMessage } from '../../utils/apiErrors'
import BookingSettingsForm from './settings/BookingSettingsForm'
import WeeklyHoursForm from './settings/WeeklyHoursForm'

/** All 422 messages keyed by field, e.g. { "days.2.ranges.0.end_time": "…" }. */
function errorsOf(error) {
  const errors = error?.response?.status === 422 ? error.response.data?.errors : null
  return errors ? Object.fromEntries(Object.entries(errors).map(([key, messages]) => [key, messages[0]])) : {}
}

/**
 * Holds the unsaved values of both forms, so other parts of the page (the
 * slot preview) can react to them before saving.
 */
function SettingsEditor({ initialSettings, initialWeek }) {
  const { t } = useTranslation()
  const toast = useToast()
  const saveSettings = useUpdateSettings()
  const saveHours = useUpdateWorkingHours()
  const [settings, setSettings] = useState(initialSettings)
  const [week, setWeek] = useState(initialWeek)

  const notifyFailure = (error) => toast.error(error?.response?.status === 422 ? t('settings.fixErrors') : errorMessage(error, t('common.networkError')))

  function handleSaveSettings() {
    const { slot_duration_minutes, booking_window_days, cancel_cutoff_hours } = settings
    saveSettings.mutate(
      {
        slot_duration_minutes: Number(slot_duration_minutes),
        booking_window_days: Number(booking_window_days),
        cancel_cutoff_hours: Number(cancel_cutoff_hours),
      },
      {
        onSuccess: (saved) => {
          setSettings((current) => ({ ...saved, customDuration: current.customDuration }))
          toast.success(t('settings.bookingSaved'))
        },
        onError: notifyFailure,
      },
    )
  }

  function handleSaveHours() {
    saveHours.mutate(week, {
      onSuccess: (saved) => {
        setWeek(saved)
        toast.success(t('settings.hoursSaved'))
      },
      onError: notifyFailure,
    })
  }

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold text-slate-900">{t('pages.settings')}</h1>
      <BookingSettingsForm
        value={settings}
        onChange={setSettings}
        errors={errorsOf(saveSettings.error)}
        onSave={handleSaveSettings}
        saving={saveSettings.isPending}
      />
      <WeeklyHoursForm
        week={week}
        onChange={setWeek}
        errors={errorsOf(saveHours.error)}
        onSave={handleSaveHours}
        saving={saveHours.isPending}
      />
    </div>
  )
}

export default function Settings() {
  const settings = useDoctorSettings()
  const hours = useWorkingHours()

  if (settings.isPending || hours.isPending) return <Loading />
  if (settings.isError || hours.isError) {
    return (
      <LoadError
        onRetry={() => {
          settings.refetch()
          hours.refetch()
        }}
      />
    )
  }

  return <SettingsEditor initialSettings={settings.data} initialWeek={hours.data} />
}
