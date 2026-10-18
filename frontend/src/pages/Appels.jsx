import { useCallback, useEffect, useState } from 'react'
import { absencesApi, appelsApi, classesApi, etudiantsApi } from '../api/endpoints'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Alert from '../components/Alert'
import { useToast } from '../components/Toast'
import { useAuth } from '../context/AuthContext'
import { Select } from '../components/Field'

const STATUTS = [
  { value: 'present', label: 'Présent', cls: 'appel-btn--present' },
  { value: 'absence', label: 'Absent', cls: 'appel-btn--absent' },
  { value: 'retard', label: 'Retard', cls: 'appel-btn--retard' },
]

export default function Appels() {
  const toast = useToast()
  const { user } = useAuth()
  const canConfirm = ['admin', 'censeur', 'cpe'].includes(user?.role)
  const [classes, setClasses] = useState([])
  const [eleves, setEleves] = useState([])
  const [statuts, setStatuts] = useState({})
  const [appel, setAppel] = useState(null)

  const [classeId, setClasseId] = useState('')
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10))
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [confirming, setConfirming] = useState(false)
  const [error, setError] = useState(null)

  const locked = appel?.statut === 'valide' || appel?.statut === 'confirme'
  const confirme = appel?.statut === 'confirme'

  useEffect(() => {
    classesApi.list({ per_page: 1000 }).then((res) => setClasses(res.data.data)).catch(() => {})
  }, [])

  const fetchAppel = useCallback(async () => {
    if (!classeId) {
      setEleves([])
      setStatuts({})
      setAppel(null)
      return
    }
    setLoading(true)
    setError(null)
    try {
      const [studentsRes, absRes, appelRes] = await Promise.all([
        etudiantsApi.list({ classe_id: classeId, per_page: 1000 }),
        absencesApi.list({ classe_id: classeId, date, per_page: 1000 }),
        appelsApi.show({ classe_id: classeId, date }),
      ])

      const students = studentsRes.data.data
      setEleves(students)
      setAppel(appelRes.data)

      const map = {}
      students.forEach((e) => {
        map[e.id] = 'present'
      })
      absRes.data.data.forEach((a) => {
        map[a.etudiant_id] = a.type === 'retard' ? 'retard' : 'absence'
      })
      setStatuts(map)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [classeId, date])

  useEffect(() => {
    fetchAppel()
  }, [fetchAppel])

  const setStatut = (etudiantId, statut) => {
    if (locked) return
    setStatuts((prev) => ({ ...prev, [etudiantId]: statut }))
  }

  const save = async (valider) => {
    if (!classeId || locked) return
    setSaving(true)
    try {
      const presences = eleves.map((e) => ({
        etudiant_id: e.id,
        statut: statuts[e.id] || 'present',
      }))
      const res = await appelsApi.save({ classe_id: classeId, date, valider, presences })
      toast.success(res.data.message)
      fetchAppel()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setSaving(false)
    }
  }

  const compte = (s) => eleves.filter((e) => (statuts[e.id] || 'present') === s).length

  const confirmer = async () => {
    if (!appel) return
    setConfirming(true)
    try {
      const res = await appelsApi.confirmer(appel.id)
      toast.success(res.data.message)
      fetchAppel()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setConfirming(false)
    }
  }

  return (
    <div>
      <div className="toolbar">
        <Select value={classeId} onChange={(e) => setClasseId(e.target.value)} className="filter-select" aria-label="Choisir une classe">
          <option value="">— Choisir une classe —</option>
          {classes.map((c) => (
            <option key={c.id} value={c.id}>{c.nom} ({c.niveau})</option>
          ))}
        </Select>
        <input
          type="date"
          className="filter-input"
          value={date}
          onChange={(e) => setDate(e.target.value)}
          aria-label="Choisir la date"
        />
      </div>

      {error && <Alert>{error}</Alert>}

      {!classeId ? (
        <EmptyState title="Choisissez une classe" description="Sélectionnez une classe et une date pour faire l'appel." />
      ) : loading ? (
        <div className="page-loader"><Spinner /></div>
      ) : eleves.length === 0 ? (
        <EmptyState title="Aucun élève" description="Cette classe ne contient aucun élève." />
      ) : (
        <div className="card">
          <div className="card-body">
            {confirme ? (
              <Alert type="success" onClose={() => {}}>
                ✅ Appel confirmé{appel?.confirme_par ? ` par ${appel.confirme_par.name}` : ''}. Les absences sont comptabilisées au bulletin.
              </Alert>
            ) : locked ? (
              <Alert type="success" onClose={() => {}}>
                🔒 Appel validé{appel?.valide_par ? ` par ${appel.valide_par.name}` : ''}. Il n'est plus modifiable.
                {canConfirm && (
                  <div style={{ marginTop: 10 }}>
                    <button type="button" className="btn btn--primary btn--sm" onClick={confirmer} disabled={confirming}>
                      {confirming ? 'Confirmation…' : "✅ Confirmer l'appel"}
                    </button>
                  </div>
                )}
              </Alert>
            ) : null}

            <div className="appel-summary">
              <span className="badge badge--success">Présents : {compte('present')}</span>
              <span className="badge badge--danger">Absents : {compte('absence')}</span>
              <span className="badge badge--amber">Retards : {compte('retard')}</span>
            </div>

            <div className="table-wrapper">
              <table className="table">
                <thead>
                  <tr>
                    <th>Élève</th>
                    <th>Matricule</th>
                    <th style={{ width: 260 }}>Statut</th>
                  </tr>
                </thead>
                <tbody>
                  {eleves.map((e) => {
                    const s = statuts[e.id] || 'present'
                    return (
                      <tr key={e.id}>
                        <td className="cell-strong">{e.prenom} {e.nom}</td>
                        <td className="cell-muted">{e.matricule || '—'}</td>
                        <td>
                          <div className="appel-buttons">
                            {STATUTS.map((opt) => (
                              <button
                                key={opt.value}
                                type="button"
                                disabled={locked}
                                className={`appel-btn ${opt.cls} ${s === opt.value ? 'appel-btn--active' : ''}`}
                                onClick={() => setStatut(e.id, opt.value)}
                              >
                                {opt.label}
                              </button>
                            ))}
                          </div>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>

            {!locked && (
              <div className="bulk-actions">
                <button type="button" className="btn btn--ghost" onClick={() => save(false)} disabled={saving}>
                  {saving ? 'Enregistrement…' : 'Enregistrer (brouillon)'}
                </button>
                <button type="button" className="btn btn--primary" onClick={() => save(true)} disabled={saving}>
                  🔒 Valider et verrouiller
                </button>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
