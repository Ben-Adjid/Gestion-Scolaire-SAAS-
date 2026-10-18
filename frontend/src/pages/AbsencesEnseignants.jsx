import { useCallback, useEffect, useState } from 'react'
import { absencesEnseignantsApi } from '../api/endpoints'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Pagination from '../components/Pagination'
import Alert from '../components/Alert'
import { useToast } from '../components/Toast'
import { formatDate } from '../utils/format'

export default function AbsencesEnseignants() {
  const toast = useToast()
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [type, setType] = useState('')
  const [page, setPage] = useState(1)

  const fetchData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const [res, statsRes] = await Promise.all([
        absencesEnseignantsApi.list({ type: type || undefined, page }),
        absencesEnseignantsApi.stats(),
      ])
      setItems(res.data.data)
      setMeta(res.data)
      setStats(statsRes.data)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [type, page])

  useEffect(() => {
    fetchData()
  }, [fetchData])

  const confirmDelete = async (item) => {
    try {
      await absencesEnseignantsApi.remove(item.id)
      toast.success('Signalement supprimé.')
      fetchData()
    } catch (err) {
      toast.error(err.message)
    }
  }

  return (
    <div>
      <div className="toolbar">
        <select
          className="filter-select"
          value={type}
          onChange={(e) => { setType(e.target.value); setPage(1) }}
          aria-label="Filtrer par type"
        >
          <option value="">Tous</option>
          <option value="absence">Absences</option>
          <option value="retard">Retards</option>
        </select>
      </div>

      {stats && (
        <div className="stat-grid stat-grid--3">
          <div className="stat-card stat-card--rose">
            <span className="stat-icon">🚫</span>
            <div className="stat-body">
              <span className="stat-value">{stats.absences}</span>
              <span className="stat-label">Absences</span>
            </div>
          </div>
          <div className="stat-card stat-card--amber">
            <span className="stat-icon">⏰</span>
            <div className="stat-body">
              <span className="stat-value">{stats.retards}</span>
              <span className="stat-label">Retards</span>
            </div>
          </div>
          <div className="stat-card stat-card--cyan">
            <span className="stat-icon">⚠️</span>
            <div className="stat-body">
              <span className="stat-value">{stats.non_justifiees}</span>
              <span className="stat-label">Non justifiées</span>
            </div>
          </div>
        </div>
      )}

      {error && <Alert>{error}</Alert>}

      <div className="card">
        <div className="card-body">
          {loading ? (
            <div className="page-loader"><Spinner /></div>
          ) : items.length === 0 ? (
            <EmptyState title="Aucun signalement" description="Aucune absence ou retard signalé." />
          ) : (
            <div className="table-wrapper">
              <table className="table">
                <thead>
                  <tr>
                    <th>Enseignant</th>
                    <th>Spécialité</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Motif</th>
                    <th className="th-actions">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((item) => (
                    <tr key={item.id}>
                      <td className="cell-strong">
                        {item.enseignant?.prenom} {item.enseignant?.nom}
                      </td>
                      <td>{item.enseignant?.specialite || '—'}</td>
                      <td>{formatDate(item.date)}</td>
                      <td>
                        <span className={`badge ${item.type === 'absence' ? 'badge--danger' : 'badge--amber'}`}>
                          {item.type === 'absence' ? 'Absence' : 'Retard'}
                        </span>
                      </td>
                      <td className="cell-muted">{item.motif || '—'}</td>
                      <td className="cell-actions">
                        <button type="button" className="icon-btn" title="Supprimer" onClick={() => confirmDelete(item)}>
                          🗑️
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
        {meta && <Pagination meta={meta} onPageChange={setPage} />}
      </div>
    </div>
  )
}
