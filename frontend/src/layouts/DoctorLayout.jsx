import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '../auth/useAuth'
import LanguageSwitcher from '../components/LanguageSwitcher'
import LogoutButton from './LogoutButton'

const LINKS = [
  { to: '/doctor', label: 'nav.dashboard', end: true },
  { to: '/doctor/schedule', label: 'nav.schedule' },
  { to: '/doctor/patients', label: 'nav.patients' },
  { to: '/doctor/reports', label: 'nav.reports' },
  { to: '/doctor/settings', label: 'nav.settings' },
]

/** Doctor area: sidebar on wide screens; on phones it opens from a Menu button. */
export default function DoctorLayout() {
  const { t } = useTranslation()
  const { user } = useAuth()
  const [menuOpen, setMenuOpen] = useState(false)

  return (
    <div className="min-h-screen bg-slate-50 md:flex">
      <header className="flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3 md:hidden">
        <span className="text-lg font-bold text-sky-700">{t('common.appName')}</span>
        <button
          type="button"
          aria-expanded={menuOpen}
          aria-controls="doctor-sidebar"
          onClick={() => setMenuOpen((open) => !open)}
          className="ms-auto rounded-lg px-3 py-1.5 text-sm font-medium text-slate-700 ring-1 ring-slate-300"
        >
          {t('nav.menu')}
        </button>
      </header>

      <aside
        id="doctor-sidebar"
        className={`${menuOpen ? 'flex' : 'hidden'} flex-col border-e border-slate-200 bg-white md:sticky md:top-0 md:flex md:h-screen md:w-60 md:shrink-0`}
      >
        <div className="hidden px-5 pt-6 pb-4 md:block">
          <span className="text-xl font-bold text-sky-700">{t('common.appName')}</span>
          <p className="mt-1 truncate text-sm text-slate-500">{user?.name}</p>
        </div>
        <nav aria-label={t('nav.main')} className="flex flex-col gap-1 px-3 py-3 md:py-0">
          {LINKS.map(({ to, label, end }) => (
            <NavLink
              key={to}
              to={to}
              end={end}
              onClick={() => setMenuOpen(false)}
              className={({ isActive }) =>
                `rounded-lg px-3 py-2 text-sm font-medium ${
                  isActive ? 'bg-sky-50 text-sky-800' : 'text-slate-600 hover:bg-slate-100'
                }`
              }
            >
              {t(label)}
            </NavLink>
          ))}
        </nav>
        <div className="flex items-center gap-2 border-t border-slate-200 px-3 py-3 md:mt-auto">
          <LanguageSwitcher />
          <LogoutButton className="ms-auto" />
        </div>
      </aside>

      <main className="min-w-0 flex-1 px-4 py-6 md:px-8">
        <Outlet />
      </main>
    </div>
  )
}
