import { useCallback, useEffect, useState } from 'react'
import { auditsApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Pagination from '../components/Pagination'
import Alert from '../components/Alert'
import { useToast } from '../components/Toast'

const MODULES = [
  'authentification',
  'notes',
  'inscriptions',
  'utilisateurs',
  'regles',
  'dossiers_examen',
]

function moduleLabel(module) {
  const labels = {
    authentification: 'Authentification',
    notes: 'Notes',
    inscriptions: 'Inscriptions',
    utilisateurs: 'Utilisateurs',
    regles: 'Règles de gestion',
    dossiers_examen: "Dossiers d'examen",
  }
  return labels[module] || module
}

function actionLabel(action) {
  const labels = {
    connexion: 'Connexion',
    déconnexion: 'Déconnexion',
    creation: 'Création',
    modification: 'Modification',
    suppression: 'Suppression',
    validation_notes: 'Validation',
    saisie_notes: 'Saisie',
  }
  return labels[action] || action
}

function formatDate(date) {
  if (!date) return '—'
  return new Date(date).toLocaleString('fr-FR')
}

export default function Audits() {
  const toast = useToast()
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [moduleFilter, setModuleFilter] = useState('')
  const [page, setPage] = useState(1)

  const fetchData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await auditsApi.index({ module: moduleFilter || undefined, page })
      setItems(res.data.data)
      setMeta(res.data)
    } catch (err) {
      setError(err.message)
      toast.error(err.message)
    } finally {
      setLoading(false)
    }
  }, [moduleFilter, page, toast])

  useEffect(() => {
    fetchData()
  }, [fetchData])

  return (
    <div>
      <PageHeader
        title="Journal d'audit"
        subtitle="Historique des actions sensibles réalisées dans l'application."
        extra={
          <select
            className="filter-select"
            value={moduleFilter}
            onChange={(e) => {
              setModuleFilter(e.target.value)
              setPage(1)
            }}
            aria-label="Filtrer par module"
          >
            <option value="">Tous les modules</option>
            {MODULES.map((m) => (
              <option key={m} value={m}>
                {moduleLabel(m)}
              </option>
            ))}
          </select>
        }
      />

      {error && <Alert>{error}</Alert>}

      <div className="card">
        <div className="card-body">
          {loading ? (
            <div className="page-loader">
              <Spinner />
            </div>
          ) : items.length === 0 ? (
            <EmptyState title="Aucune action" description="Aucune action enregistrée pour le moment." />
          ) : (
            <div className="table-wrapper">
              <table className="table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Utilisateur</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th>Description</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((item) => (
                    <tr key={item.id}>
                      <td className="cell-muted">{formatDate(item.created_at)}</td>
                      <td className="cell-strong">
                        {item.user ? item.user.name : '—'}
                        {item.user && (
                          <span className="muted" style={{ marginLeft: 6 }}>
                            ({item.user.role})
                          </span>
                        )}
                      </td>
                      <td>
                        <span className="badge badge--neutral">{actionLabel(item.action)}</span>
                      </td>
                      <td>
                        <span className="badge">{moduleLabel(item.module)}</span>
                      </td>
                      <td className="cell-muted">{item.description || '—'}</td>
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
