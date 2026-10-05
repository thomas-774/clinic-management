import { useTranslation } from 'react-i18next'
import LanguageSwitcher from '../components/LanguageSwitcher'

/** Centered card used by the Login and Register pages. */
export default function AuthLayout({ title, children, footer }) {
  const { t } = useTranslation()

  return (
    <main className="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-10">
      <div className="w-full max-w-md">
        <div className="mb-6 flex items-center justify-between">
          <span className="text-lg font-bold text-sky-700">{t('common.appName')}</span>
          <LanguageSwitcher />
        </div>
        <div className="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
          <h1 className="text-2xl font-bold text-slate-900">{title}</h1>
          <div className="mt-6">{children}</div>
        </div>
        {footer && <div className="mt-4 text-center text-sm text-slate-600">{footer}</div>}
      </div>
    </main>
  )
}
