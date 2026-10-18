export default function Alert({ type = 'error', children, onClose }) {
  return (
    <div className={`alert alert--${type}`} role="alert">
      <span className="alert-text">{children}</span>
      {onClose && (
        <button type="button" className="alert-close" onClick={onClose} aria-label="Fermer">
          &times;
        </button>
      )}
    </div>
  )
}
