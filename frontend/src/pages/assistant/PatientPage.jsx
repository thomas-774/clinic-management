import { useTranslation } from 'react-i18next'
import PagePlaceholder from '../../components/PagePlaceholder'

/** Built in a later Phase 8 task. */
export default function PatientPage() {
  const { t } = useTranslation()
  return <PagePlaceholder title={t('pages.patientDetails')} />
}
