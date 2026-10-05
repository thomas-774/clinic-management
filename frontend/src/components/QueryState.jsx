import { useTranslation } from 'react-i18next'

/** Loading line for a page or section. */
export function Loading() {
  const { t } = useTranslation()
  return (
    <p role="status" className="py-8 text-center text-slate-500">
      {t('common.loading')}
    </p>
  )
}

/** Error box with a retry button. */
export function LoadError({ onRetry }) {
  const { t } = useTranslation()
  return (
    <div role="alert" className="rounded-xl bg-red-50 p-4 text-red-700">
      <p>{t('common.loadError')}</p>
      {onRetry && (
        <button type="button" onClick={onRetry} className="mt-2 text-sm font-semibold underline">
          {t('common.retry')}
        </button>
      )}
    </div>
  )
}
