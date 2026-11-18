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
  'Paiement',
  'FraisScolaire',
  'inscriptions',
  'utilisateurs',
  'regles',
  'dossiers_examen',
]

// Libellés lisibles des champs tracés (notes, paiements, frais).
const CHAMPS = {
  valeur: 'Note',
  appreciation: 'Appréciation',
  statut: 'Statut',
  trimestre: 'Trimestre',
  type: 'Type',
  montant: 'Montant',
  mode: 'Mode',
  date: 'Date',
  date_echeance: "Date d'échéance",
  reference: 'Référence',
  libelle: 'Libellé',
  frais_id: 'Frais n°',
}

// Affiche uniquement les champs qui ont changé : « Note : 10 → 14 ».
function differences(avant, apres) {
  if (!avant && !apres) return []
  const cles = [...new Set([...Object.keys(avant || {}), ...Object.keys(apres || {})])]
  return cles
    .filter((c) => String((avant || {})[c] ?? '—') !== String((apres || {})[c] ?? '—'))
    .map((c) => `${CHAMPS[c] || c} : ${(avant || {})[c] ?? '—'} → ${(apres || {})[c] ?? '—'}`)
}

function moduleLabel(module) {
  const labels = {
    authentification: 'Authentification',
    notes: 'Notes',
    Paiement: 'Paiements',
    FraisScolaire: 'Frais scolaires',
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
  const [modificationsSeules, setModificationsSeules] = useState(false)
  const [page, setPage] = useState(1)

  const fetchData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await auditsApi.index({
        module: moduleFilter || undefined,
        modifications: modificationsSeules ? 1 : undefined,
        page,
      })
      setItems(res.data.data)
      setMeta(res.data)
    } catch (err) {
      setError(err.message)
      toast.error(err.message)
    } finally {
      setLoading(false)
    }
  }, [moduleFilter, modificationsSeules, page, toast])

  useEffect(() => {
    fetchData()
  }, [fetchData])

  return (
    <div>
      <PageHeader
        title="Journal d'audit"
        subtitle="Historique des actions sensibles réalisées dans l'application."
        extra={
          <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
          <label style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 14 }}>
            <input
              type="checkbox"
              checked={modificationsSeules}
              onChange={(e) => { setModificationsSeules(e.target.checked); setPage(1) }}
            />
            Modifications uniquement
          </label>
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
          </div>
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
                    <th>Modifications</th>
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
                      <td className="cell-muted" style={{ fontSize: 13 }}>
                        {differences(item.valeurs_avant, item.valeurs_apres).length === 0
                          ? '—'
                          : differences(item.valeurs_avant, item.valeurs_apres).map((d) => <div key={d}>{d}</div>)}
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
