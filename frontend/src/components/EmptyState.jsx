export default function EmptyState({ title, description, action }) {
  return (
    <div className="empty-state">
      <div className="empty-state-icon">📚</div>
      <h3>{title || 'Aucune donnée'}</h3>
      {description && <p>{description}</p>}
      {action}
    </div>
  )
}
