import Modal from './Modal'

export default function ConfirmDialog({
  open,
  title = 'Confirmer la suppression',
  message,
  onConfirm,
  onCancel,
  loading = false,
}) {
  return (
    <Modal
      open={open}
      title={title}
      onClose={onCancel}
      size="sm"
      footer={
        <>
          <button type="button" className="btn btn--ghost" onClick={onCancel} disabled={loading}>
            Annuler
          </button>
          <button
            type="button"
            className="btn btn--danger"
            onClick={onConfirm}
            disabled={loading}
          >
            {loading ? 'Suppression…' : 'Supprimer'}
          </button>
        </>
      }
    >
      <div className="confirm-message">
        <div className="confirm-icon">!</div>
        <p>{message || 'Cette action est irréversible. Voulez-vous continuer ?'}</p>
      </div>
    </Modal>
  )
}
