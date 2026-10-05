import { useTranslation } from 'react-i18next'

/** Toggles Arabic ⇄ English; the label is the other language's own name. */
export default function LanguageSwitcher({ className = '' }) {
  const { t, i18n } = useTranslation()
  const next = i18n.resolvedLanguage === 'ar' ? 'en' : 'ar'

  return (
    <button
      type="button"
      lang={next}
      title={t('language.change')}
      onClick={() => i18n.changeLanguage(next)}
      className={`rounded-lg px-3 py-1.5 text-sm font-medium text-slate-600 ring-1 ring-slate-300 hover:bg-slate-100 ${className}`}
    >
      {t('language.other')}
    </button>
  )
}
