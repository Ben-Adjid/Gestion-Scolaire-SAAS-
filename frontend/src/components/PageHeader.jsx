export default function PageHeader({
  title,
  subtitle,
  search,
  onSearch,
  searchPlaceholder,
  actionLabel,
  onAction,
  extra,
}) {
  return (
    <div className="page-header">
      <div className="page-header-info">
        <h1>{title}</h1>
        {subtitle && <p>{subtitle}</p>}
      </div>
      <div className="page-header-actions">
        {onSearch && (
          <div className="search-bar">
            <svg
              className="search-icon"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <circle cx="11" cy="11" r="7" />
              <path d="m21 21-4.3-4.3" />
            </svg>
            <input
              type="search"
              value={search}
              onChange={(e) => onSearch(e.target.value)}
              placeholder={searchPlaceholder || 'Rechercher…'}
            />
          </div>
        )}
        {extra}
        {actionLabel && (
          <button type="button" className="btn btn--primary" onClick={onAction}>
            <span className="btn-plus">+</span> {actionLabel}
          </button>
        )}
      </div>
    </div>
  )
}
