import { useEffect, useState } from 'react'
import client from '../api/client'
import Spinner from './Spinner'
import Alert from './Alert'

const ACTIONS = {
  creation_etablissement: 'Création',
  modification_etablissement: 'Modification',
  suspension_etablissement: 'Suspension',
  reactivation_etablissement: 'Réactivation',
  suppression_etablissement: 'Suppression',
  renouvellement_abonnement: 'Renouvellement',
  creation_groupe: 'Groupe créé',
  rattachement_groupe: 'Rattachement',
  support_repondu: 'Réponse au support',
  annonce_plateforme: 'Annonce',
}

// Trace des actions de la plateforme : qui a fait quoi, sur quel établissement, et quand.
export default function JournalPlateformeCard() {
  const [page, setPage] = useState(null)
  const [numero, setNumero] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    setLoading(true)
    client
      .get('/super-admin/journal', { params: { page: numero } })
      .then((res) => setPage(res.data))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false))
  }, [numero])

  const lignes = page?.data || []

  return (
    <section className="card" style={{ marginBottom: 16 }}>
      <div className="card-header">
        <h2>Journal de la plateforme</h2>
        <span className="muted">Actions du personnel de la plateforme</span>
      </div>
      <div className="card-body">
        {error && <Alert>{error}</Alert>}
        {loading ? (
          <Spinner />
        ) : lignes.length === 0 ? (
          <p className="muted">Aucune action enregistrée pour le moment.</p>
        ) : (
          <div className="table-wrapper">
            <table className="table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Action</th>
                  <th>Détail</th>
                  <th>Par</th>
                </tr>
              </thead>
              <tbody>
                {lignes.map((a) => (
                  <tr key={a.id}>
                    <td className="cell-muted">{new Date(a.created_at).toLocaleString('fr-FR')}</td>
                    <td>{ACTIONS[a.action] || a.action}</td>
                    <td>{a.description}</td>
                    <td className="cell-muted">{a.user?.name || '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {page && page.last_page > 1 && (
          <div className="toolbar" style={{ marginTop: 12, justifyContent: 'flex-end' }}>
            <button type="button" className="btn btn--ghost btn--sm" disabled={numero <= 1} onClick={() => setNumero((n) => n - 1)}>
              Précédent
            </button>
            <span className="muted">Page {page.current_page} sur {page.last_page}</span>
            <button type="button" className="btn btn--ghost btn--sm" disabled={numero >= page.last_page} onClick={() => setNumero((n) => n + 1)}>
              Suivant
            </button>
          </div>
        )}
      </div>
    </section>
  )
}
