import { useEffect, useId, useRef } from 'react'
import { useTranslation } from 'react-i18next'

/**
 * Accessible dialog: closes on Escape or the backdrop, moves focus inside on
 * open and back to where it was on close.
 */
export default function Modal({ open, title, onClose, children, size = 'md' }) {
  const { t } = useTranslation()
  const titleId = useId()
  const panelRef = useRef(null)
  // Latest onClose without re-running the focus effect on every render.
  const onCloseRef = useRef(onClose)
  useEffect(() => {
    onCloseRef.current = onClose
  })

  useEffect(() => {
    if (!open) return
    const previous = document.activeElement
    const first = panelRef.current?.querySelector('input, select, textarea, button:not([data-close])')
    ;(first ?? panelRef.current)?.focus()

    const onKey = (event) => event.key === 'Escape' && onCloseRef.current()
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('keydown', onKey)
      previous?.focus?.()
    }
  }, [open])

  if (!open) return null

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-0 sm:items-center sm:p-4" onMouseDown={onClose}>
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        tabIndex={-1}
        onMouseDown={(event) => event.stopPropagation()}
        className={`max-h-[92vh] w-full overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl sm:p-6 ${
          size === 'lg' ? 'sm:max-w-2xl' : 'sm:max-w-lg'
        }`}
      >
        <div className="mb-4 flex items-start justify-between gap-4">
          <h2 id={titleId} className="text-lg font-bold text-slate-900">
            {title}
          </h2>
          <button
            type="button"
            data-close
            onClick={onClose}
            aria-label={t('common.close')}
            className="-m-1 rounded-lg p-1 text-xl leading-none text-slate-500 hover:bg-slate-100"
          >
            ×
          </button>
        </div>
        {children}
      </div>
    </div>
  )
}
