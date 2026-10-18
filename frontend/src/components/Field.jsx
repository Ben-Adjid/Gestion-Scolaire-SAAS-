export function Field({ label, error, hint, required, children, htmlFor }) {
  return (
    <div className={`field ${error ? 'field--error' : ''}`}>
      {label && (
        <label className="field-label" htmlFor={htmlFor}>
          {label}
          {required && <span className="field-required"> *</span>}
        </label>
      )}
      {children}
      {hint && !error && <p className="field-hint">{hint}</p>}
      {error && <p className="field-error">{error}</p>}
    </div>
  )
}

export function Input({ id, error, ...props }) {
  return <input id={id} className={`input ${error ? 'input--error' : ''}`} {...props} />
}

export function Select({ id, error, children, ...props }) {
  return (
    <select id={id} className={`input ${error ? 'input--error' : ''}`} {...props}>
      {children}
    </select>
  )
}

export function Textarea({ id, error, ...props }) {
  return <textarea id={id} className={`input ${error ? 'input--error' : ''}`} {...props} />
}
