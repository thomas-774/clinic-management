import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'
import Card from '../../components/Card'
import ConfirmDialog from '../../components/ConfirmDialog'
import HistoryList from '../../components/HistoryList'
import { LoadError, Loading } from '../../components/QueryState'
import { useDeleteHistoryEntry, usePatient } from '../../hooks/usePatient'
import { formatDate } from '../../utils/format'
import EditPatientModal from './EditPatientModal'
import { HISTORY_TYPES } from '../../utils/historyTypes'
import HistoryEntryModal from './HistoryEntryModal'

function InfoItem({ label, children }) {
  return (
    <div>
      <dt className="text-xs text-slate-500">{label}</dt>
      <dd className="text-slate-900">{children || '—'}</dd>
    </div>
  )
}

const smallButton = 'rounded-md px-2 py-1 text-xs font-medium hover:bg-slate-100'

export default function PatientDetails() {
  const { t } = useTranslation()
  const id = Number(useParams().id)
  const { data: patient, isPending, isError, error, refetch } = usePatient(id)
  const deleteEntry = useDeleteHistoryEntry(id)
  const [editingInfo, setEditingInfo] = useState(false)
  const [entryForm, setEntryForm] = useState(null) // null = closed, {} = new, entry = edit
  const [deleting, setDeleting] = useState(null)
  const [typeFilter, setTypeFilter] = useState('all')

  if (isPending) return <Loading />
  if (isError) {
    return error?.response?.status === 404 ? (
      <p className="py-8 text-center text-slate-500">{t('patientDetails.notFound')}</p>
    ) : (
      <LoadError onRetry={refetch} />
    )
  }

  const history = typeFilter === 'all' ? patient.history : patient.history.filter((entry) => entry.type === typeFilter)
  const typesInUse = HISTORY_TYPES.filter((type) => patient.history.some((entry) => entry.type === type))

  return (
    <div className="space-y-4">
      <Link to="/doctor/patients" className="text-sm text-sky-700 hover:underline">
        {t('patientDetails.back')}
      </Link>

      <Card
        action={
          <button type="button" onClick={() => setEditingInfo(true)} className="text-sm font-semibold text-sky-700 hover:underline">
            {t('patientDetails.edit')}
          </button>
        }
        title={<span className="text-2xl font-bold">{patient.name}</span>}
      >
        <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <InfoItem label={t('patientForm.phone')}>
            <span dir="ltr">{patient.phone}</span>
          </InfoItem>
          <InfoItem label={t('patientForm.address')}>{patient.address}</InfoItem>
          <InfoItem label={t('patientForm.dateOfBirth')}>{patient.date_of_birth && formatDate(patient.date_of_birth)}</InfoItem>
          <InfoItem label={t('patientForm.gender')}>{patient.gender && t(`patientForm.${patient.gender}`)}</InfoItem>
        </dl>
        <div className="mt-4 rounded-xl bg-sky-50 p-3">
          <p className="text-xs font-medium text-sky-800">{t('patientForm.currentIllness')}</p>
          <p className="text-slate-900">{patient.current_illness || t('patientHome.noIllness')}</p>
        </div>
      </Card>

      <Card
        title={t('patientDetails.history')}
        action={
          <button
            type="button"
            onClick={() => setEntryForm({})}
            className="rounded-lg bg-sky-700 px-3 py-1.5 text-sm font-semibold text-white hover:bg-sky-800"
          >
            {t('patientDetails.addEntry')}
          </button>
        }
      >
        {typesInUse.length > 1 && (
          <div role="group" aria-label={t('patientDetails.filterByType')} className="mb-3 flex flex-wrap gap-2">
            {['all', ...typesInUse].map((type) => (
              <button
                key={type}
                type="button"
                aria-pressed={typeFilter === type}
                onClick={() => setTypeFilter(type)}
                className={`rounded-full px-3 py-1 text-xs font-medium ring-1 ${
                  typeFilter === type ? 'bg-sky-700 text-white ring-sky-700' : 'text-slate-600 ring-slate-300 hover:bg-slate-50'
                }`}
              >
                {type === 'all' ? t('patientDetails.allTypes') : t(`history.types.${type}`)}
              </button>
            ))}
          </div>
        )}
        <HistoryList
          entries={history}
          mode="detailed"
          renderActions={(entry) => (
            <>
              <button type="button" onClick={() => setEntryForm(entry)} className={`${smallButton} text-sky-700`} aria-label={t('patientDetails.editEntry', { title: entry.title })}>
                {t('patientDetails.edit')}
              </button>
              <button type="button" onClick={() => setDeleting(entry)} className={`${smallButton} text-red-600`} aria-label={t('patientDetails.deleteEntry', { title: entry.title })}>
                {t('patientDetails.delete')}
              </button>
            </>
          )}
        />
      </Card>

      <Card title={t('patientDetails.visits')}>
        {/* Visit timeline with balances arrives in T5-10. */}
        <p className="text-sm text-slate-500">{t('patientDetails.noVisits')}</p>
      </Card>

      {editingInfo && <EditPatientModal patient={patient} onClose={() => setEditingInfo(false)} />}
      {entryForm && (
        <HistoryEntryModal patientId={patient.id} entry={entryForm.id ? entryForm : null} onClose={() => setEntryForm(null)} />
      )}
      <ConfirmDialog
        open={Boolean(deleting)}
        title={t('patientDetails.deleteTitle')}
        message={t('patientDetails.deleteMessage', { title: deleting?.title })}
        confirmLabel={t('patientDetails.delete')}
        busy={deleteEntry.isPending}
        onCancel={() => setDeleting(null)}
        onConfirm={() => deleteEntry.mutate(deleting.id, { onSuccess: () => setDeleting(null) })}
      />
    </div>
  )
}
