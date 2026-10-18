import { useEffect } from 'react'

export default function Modal({ open, title, onClose, children, footer, size = 'md', center = false }) {
  useEffect(() => {
    if (!open) return

    const onKeyDown = (e) => {
      if (e.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', onKeyDown)
    document.body.style.overflow = 'hidden'

    return () => {
      document.removeEventListener('keydown', onKeyDown)
      document.body.style.overflow = ''
    }
  }, [open, onClose])

  if (!open) return null

  return (
    <div className={`modal-overlay ${center ? 'modal-overlay--center' : ''}`} onMouseDown={onClose}>
      <div
        className={`modal modal--${size}`}
        role="dialog"
        aria-modal="true"
        aria-label={title || 'Fenêtre'}
        onMouseDown={(e) => e.stopPropagation()}
      >
        {title ? (
          <header className="modal-header">
            <h2 className="modal-title">{title}</h2>
            <button type="button" className="modal-close" onClick={onClose} aria-label="Fermer">
              &times;
            </button>
          </header>
        ) : (
          <button type="button" className="modal-close modal-close--float" onClick={onClose} aria-label="Fermer">
            &times;
          </button>
        )}
        <div className="modal-body">{children}</div>
        {footer && <footer className="modal-footer">{footer}</footer>}
      </div>
    </div>
  )
}
