import { useTranslation } from 'react-i18next'
import PagePlaceholder from '../../components/PagePlaceholder'

export default function Schedule() {
  const { t } = useTranslation()
  return <PagePlaceholder title={t('pages.schedule')} />
}
