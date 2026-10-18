import { useCallback, useEffect, useState } from 'react'
import { pointagesApi } from '../api/endpoints'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Alert from '../components/Alert'

function statutBadge(statut) {
  if (statut === 'a_l_heure') return { label: "À l'heure", cls: 'badge--success' }
  if (statut === 'retard') return { label: 'En retard', cls: 'badge--amber' }
  return { label: 'Absent', cls: 'badge--danger' }
}

export default function Pointages() {
  const [items, setItems] = useState([])
  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const fetchData = useCallback(async () => {
    try {
      const [listRes, statsRes] = await Promise.all([
        pointagesApi.list(),
        pointagesApi.stats(),
      ])
      setItems(listRes.data)
      setStats(statsRes.data)
      setError(null)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    fetchData()
    const timer = setInterval(fetchData, 30000)
    return () => clearInterval(timer)
  }, [fetchData])

  return (
    <div>
      {error && <Alert>{error}</Alert>}

      {stats && (
        <div className="stat-grid stat-grid--4">
          <div className="stat-card stat-card--indigo">
            <span className="stat-icon">👩‍🏫</span>
            <div className="stat-body">
              <span className="stat-value">{stats.total}</span>
              <span className="stat-label">Enseignants</span>
            </div>
          </div>
          <div className="stat-card stat-card--green">
            <span className="stat-icon">✅</span>
            <div className="stat-body">
              <span className="stat-value">{stats.pointes}</span>
              <span className="stat-label">Pointés</span>
            </div>
          </div>
          <div className="stat-card stat-card--amber">
            <span className="stat-icon">⏰</span>
            <div className="stat-body">
              <span className="stat-value">{stats.retards}</span>
              <span className="stat-label">En retard</span>
            </div>
          </div>
          <div className="stat-card stat-card--rose">
            <span className="stat-icon">🚫</span>
            <div className="stat-body">
              <span className="stat-value">{stats.absents}</span>
              <span className="stat-label">Absents</span>
            </div>
          </div>
        </div>
      )}

      <div className="card">
        <div className="card-body">
          {loading ? (
            <div className="page-loader"><Spinner /></div>
          ) : items.length === 0 ? (
            <EmptyState title="Aucun enseignant" description="Aucun enseignant enregistré." />
          ) : (
            <div className="table-wrapper">
              <table className="table">
                <thead>
                  <tr>
                    <th>Enseignant</th>
                    <th>Spécialité</th>
                    <th>Code</th>
                    <th>Heure</th>
                    <th>Statut</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((item) => {
                    const s = statutBadge(item.statut)
                    return (
                      <tr key={item.enseignant.id}>
                        <td className="cell-strong">
                          {item.enseignant.prenom} {item.enseignant.nom}
                        </td>
                        <td>{item.enseignant.specialite || '—'}</td>
                        <td className="cell-muted">{item.enseignant.code_pointe}</td>
                        <td className="cell-muted">{item.heure || '—'}</td>
                        <td><span className={`badge ${s.cls}`}>{s.label}</span></td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
