import { useCallback, useEffect, useState } from 'react'
import client from '../api/client'
import Spinner from './Spinner'
import Alert from './Alert'
import { useToast } from './Toast'

// Surveillance de la production : erreurs serveur regroupées, avec leur nombre d'occurrences.
export default function ErreursServeurCard() {
  const toast = useToast()
  const [page, setPage] = useState(null)
  const [numero, setNumero] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const charger = useCallback(() => {
    setLoading(true)
    client
      .get('/super-admin/erreurs', { params: { page: numero } })
      .then((res) => setPage(res.data))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false))
  }, [numero])

  useEffect(() => { charger() }, [charger])

  const resoudre = async (erreur) => {
    try {
      const res = await client.delete(`/super-admin/erreurs/${erreur.id}`)
      toast.success(res.data.message)
      charger()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const lignes = page?.data || []

  return (
    <section className="card" style={{ marginBottom: 16 }}>
      <div className="card-header">
        <h2>Erreurs serveur</h2>
        <span className="muted">Regroupées par origine ; une alerte e-mail par heure au plus en production</span>
      </div>
      <div className="card-body">
        {error && <Alert>{error}</Alert>}
        {loading ? (
          <Spinner />
        ) : lignes.length === 0 ? (
          <p className="muted">Aucune erreur enregistrée. Tout fonctionne normalement.</p>
        ) : (
          <div className="table-wrapper">
            <table className="table">
              <thead>
                <tr>
                  <th>Dernière fois</th>
                  <th>Erreur</th>
                  <th>Origine</th>
                  <th>Occurrences</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {lignes.map((e) => (
                  <tr key={e.id}>
                    <td className="cell-muted">{e.derniere_fois ? new Date(e.derniere_fois).toLocaleString('fr-FR') : '—'}</td>
                    <td>
                      <strong>{e.classe.split('\\').pop()}</strong>
                      <div className="cell-muted" style={{ fontSize: 13 }}>{e.message}</div>
                    </td>
                    <td className="cell-muted" style={{ fontSize: 12 }}>
                      {e.fichier}:{e.ligne}
                      {e.url && <div>{e.methode} /{e.url}</div>}
                    </td>
                    <td><span className={`badge ${e.occurrences > 10 ? 'badge--danger' : 'badge--amber'}`}>{e.occurrences}</span></td>
                    <td>
                      <button type="button" className="btn btn--ghost btn--sm" onClick={() => resoudre(e)}>Résolue</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {page && page.last_page > 1 && (
          <div className="toolbar" style={{ marginTop: 12, justifyContent: 'flex-end' }}>
            <button type="button" className="btn btn--ghost btn--sm" disabled={numero <= 1} onClick={() => setNumero((n) => n - 1)}>Précédent</button>
            <span className="muted">Page {page.current_page} sur {page.last_page}</span>
            <button type="button" className="btn btn--ghost btn--sm" disabled={numero >= page.last_page} onClick={() => setNumero((n) => n + 1)}>Suivant</button>
          </div>
        )}
      </div>
    </section>
  )
}
