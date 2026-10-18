export default function Pagination({ meta, onPageChange }) {
  if (!meta || meta.last_page <= 1) return null

  const { current_page: current, last_page: last, total } = meta

  const pages = []
  for (let i = 1; i <= last; i++) {
    if (i === 1 || i === last || (i >= current - 1 && i <= current + 1)) {
      pages.push(i)
    } else if (pages[pages.length - 1] !== '…') {
      pages.push('…')
    }
  }

  return (
    <div className="pagination">
      <span className="pagination-info">
        {total} résultat{total > 1 ? 's' : ''}
      </span>
      <div className="pagination-buttons">
        <button
          type="button"
          className="page-btn"
          disabled={current <= 1}
          onClick={() => onPageChange(current - 1)}
        >
          Précédent
        </button>
        {pages.map((page, index) =>
          page === '…' ? (
            <span key={`gap-${index}`} className="page-gap">
              …
            </span>
          ) : (
            <button
              key={page}
              type="button"
              className={`page-btn ${page === current ? 'page-btn--active' : ''}`}
              onClick={() => onPageChange(page)}
            >
              {page}
            </button>
          )
        )}
        <button
          type="button"
          className="page-btn"
          disabled={current >= last}
          onClick={() => onPageChange(current + 1)}
        >
          Suivant
        </button>
      </div>
    </div>
  )
}
