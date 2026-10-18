import { useCallback, useEffect, useState } from 'react'
import { appelsApi } from '../api/endpoints'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Alert from '../components/Alert'
import { useToast } from '../components/Toast'
import { formatDate } from '../utils/format'

export default function AppelsAConfirmer() {
  const toast = useToast()
  const [items, setItems] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [confirmingId, setConfirmingId] = useState(null)

  const fetchData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await appelsApi.enAttente()
      setItems(res.data)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    fetchData()
  }, [fetchData])

  const confirmer = async (appel) => {
    setConfirmingId(appel.id)
    try {
      const res = await appelsApi.confirmer(appel.id)
      toast.success(res.data.message)
      fetchData()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setConfirmingId(null)
    }
  }

  return (
    <div>
      {error && <Alert>{error}</Alert>}

      <div className="card">
        <div className="card-body">
          {loading ? (
            <div className="page-loader"><Spinner /></div>
          ) : items.length === 0 ? (
            <EmptyState
              title="Aucun appel à confirmer"
              description="Tous les appels validés par les enseignants ont été confirmés."
            />
          ) : (
            <div className="table-wrapper">
              <table className="table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Classe</th>
                    <th>Enseignant</th>
                    <th>Absents</th>
                    <th className="th-actions">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((item) => (
                    <tr key={item.id}>
                      <td className="cell-strong">{formatDate(item.date)}</td>
                      <td><span className="badge">{item.classe?.nom}</span></td>
                      <td>{item.user?.name || '—'}</td>
                      <td className="cell-danger">{item.nb_absents ?? 0}</td>
                      <td className="cell-actions">
                        <button
                          type="button"
                          className="btn btn--primary btn--sm"
                          onClick={() => confirmer(item)}
                          disabled={confirmingId === item.id}
                        >
                          {confirmingId === item.id ? 'Confirmation…' : "✅ Confirmer l'appel"}
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
