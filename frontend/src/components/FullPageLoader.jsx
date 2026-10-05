import { useTranslation } from 'react-i18next'

export default function FullPageLoader() {
  const { t } = useTranslation()

  return (
    <div role="status" className="flex min-h-screen items-center justify-center text-slate-500">
      {t('common.loading')}
    </div>
  )
}
