import { useCallback, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ToastContext } from './useToast'

const DURATION_MS = 4000
let nextId = 1

/** Small notifications in the bottom corner; they disappear after a few seconds. */
export function ToastProvider({ children }) {
  const { t } = useTranslation()
  const [toasts, setToasts] = useState([])

  const dismiss = useCallback((id) => setToasts((current) => current.filter((toast) => toast.id !== id)), [])

  const show = useCallback(
    (type, text) => {
      const id = nextId++
      setToasts((current) => [...current, { id, type, text }])
      setTimeout(() => dismiss(id), DURATION_MS)
    },
    [dismiss],
  )

  const value = useMemo(
    () => ({ success: (text) => show('success', text), error: (text) => show('error', text) }),
    [show],
  )

  return (
    <ToastContext.Provider value={value}>
      {children}
      <div aria-live="polite" className="pointer-events-none fixed inset-x-0 bottom-4 z-[60] flex flex-col items-center gap-2 px-4 sm:items-end sm:pe-6 print:hidden">
        {toasts.map((toast) => (
          <div
            key={toast.id}
            role={toast.type === 'error' ? 'alert' : 'status'}
            className={`pointer-events-auto flex max-w-sm items-start gap-3 rounded-xl px-4 py-3 text-sm shadow-lg ring-1 ${
              toast.type === 'error' ? 'bg-red-50 text-red-800 ring-red-200' : 'bg-green-50 text-green-800 ring-green-200'
            }`}
          >
            <span className="flex-1">{toast.text}</span>
            <button type="button" onClick={() => dismiss(toast.id)} aria-label={t('common.close')} className="leading-none opacity-70 hover:opacity-100">
              ×
            </button>
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  )
}
