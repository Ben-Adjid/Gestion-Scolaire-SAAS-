import { useCallback, useEffect, useState } from 'react'
import { classesApi, emploiDuTempsApi, enseignantsApi, matieresApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Modal from '../components/Modal'
import ConfirmDialog from '../components/ConfirmDialog'
import Spinner from '../components/Spinner'
import Alert from '../components/Alert'
import { useToast } from '../components/Toast'
import { useAuth } from '../context/AuthContext'
import { Field, Input, Select } from '../components/Field'

const JOURS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi']

const NIVEAUX_ORDRE = ['6ème', '5ème', '4ème', '3ème', 'Seconde', 'Première', 'Terminale']

function sortClasses(list) {
  return [...list].sort((a, b) => {
    const na = NIVEAUX_ORDRE.indexOf(a.niveau)
    const nb = NIVEAUX_ORDRE.indexOf(b.niveau)
    const va = na === -1 ? 99 : na
    const vb = nb === -1 ? 99 : nb
    if (va !== vb) return va - vb
    return a.nom.localeCompare(b.nom)
  })
}

const EMPTY_FORM = { classe_id: '', matiere_id: '', enseignant_id: '', jour: 1, creneau_id: '', salle: '' }

export default function EmploiDuTemps() {
  const toast = useToast()
  const { user } = useAuth()
  const canEdit = ['admin', 'censeur'].includes(user?.role)
  const isTeacher = user?.role === 'enseignant'

  const [classes, setClasses] = useState([])
  const [enseignants, setEnseignants] = useState([])
  const [matieres, setMatieres] = useState([])
  const [creneaux, setCreneaux] = useState([])
  const [entries, setEntries] = useState([])

  const [mode, setMode] = useState('classe')
  const [classeId, setClasseId] = useState('')
  const [enseignantId, setEnseignantId] = useState('')
  const [loading, setLoading] = useState(false)

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(EMPTY_FORM)
  const [formErrors, setFormErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const [deleting, setDeleting] = useState(null)
  const [deleteLoading, setDeleteLoading] = useState(false)

  useEffect(() => {
    classesApi.list({ per_page: 1000 }).then((res) => {
      const sorted = sortClasses(res.data.data)
      setClasses(sorted)
      if (sorted.length > 0) setClasseId(sorted[0].id)
    }).catch(() => {})
    enseignantsApi.list({ per_page: 1000 }).then((res) => setEnseignants(res.data.data)).catch(() => {})
    matieresApi.list({ per_page: 1000 }).then((res) => setMatieres(res.data.data)).catch(() => {})
    emploiDuTempsApi.creneaux().then((res) => setCreneaux(res.data)).catch(() => {})
  }, [])

  const fetchEntries = useCallback(async () => {
    if (mode === 'enseignant' && !enseignantId) {
      setEntries([])
      return
    }
    setLoading(true)
    try {
      const params = mode === 'classe' ? { classe_id: classeId || undefined } : { enseignant_id: enseignantId || undefined }
      const res = await emploiDuTempsApi.list(params)
      setEntries(res.data.emploi_du_temps)
    } catch (err) {
      toast.error(err.message)
    } finally {
      setLoading(false)
    }
  }, [mode, classeId, enseignantId, toast])

  useEffect(() => {
    fetchEntries()
  }, [fetchEntries])

  const openCell = (jour, creneau) => {
    if (!canEdit) return
    const existing = entries.find((e) => e.jour === jour && e.creneau_id === creneau.id)
    if (existing) {
      setEditing(existing)
      setForm({
        classe_id: existing.classe_id,
        matiere_id: existing.matiere_id,
        enseignant_id: existing.enseignant_id || '',
        jour: existing.jour,
        creneau_id: existing.creneau_id,
        salle: existing.salle || '',
      })
      setFormErrors({})
      setModalOpen(true)
    } else {
      setEditing(null)
      setForm({
        ...EMPTY_FORM,
        classe_id: mode === 'classe' ? classeId : '',
        jour,
        creneau_id: creneau.id,
      })
      setFormErrors({})
      setModalOpen(true)
    }
  }

  const handleChange = (e) => {
    const { name, value } = e.target
    setForm((prev) => ({ ...prev, [name]: value }))
    setFormErrors((prev) => ({ ...prev, [name]: undefined }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setFormErrors({})
    const payload = { ...form, jour: Number(form.jour), enseignant_id: form.enseignant_id || null }
    try {
      if (editing) {
        await emploiDuTempsApi.update(editing.id, payload)
        toast.success('Cours mis à jour avec succès.')
      } else {
        await emploiDuTempsApi.create(payload)
        toast.success('Cours programmé avec succès.')
      }
      setModalOpen(false)
      fetchEntries()
    } catch (err) {
      setFormErrors(err.errors || {})
      if (!err.errors || !Object.keys(err.errors).length) {
        toast.error(err.message)
      }
    } finally {
      setSaving(false)
    }
  }

  const confirmDelete = async () => {
    setDeleteLoading(true)
    try {
      await emploiDuTempsApi.remove(deleting.id)
      toast.success('Cours retiré de l\'emploi du temps.')
      setDeleting(null)
      fetchEntries()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setDeleteLoading(false)
    }
  }

  const cellFor = (jour, creneauId) => entries.find((e) => e.jour === jour && e.creneau_id === creneauId)

  const classeIndex = classes.findIndex((c) => c.id == classeId)
  const goPrev = () => {
    if (classeIndex > 0) setClasseId(classes[classeIndex - 1].id)
  }
  const goNext = () => {
    if (classeIndex < classes.length - 1) setClasseId(classes[classeIndex + 1].id)
  }

  return (
    <div>
      <PageHeader
        title="Emploi du temps"
        subtitle={
          isTeacher
            ? 'Vos cours de la semaine.'
            : 'Consultez et programmez les cours de la semaine.'
        }
        extra={
          <>
            {!isTeacher && (
              <div className="view-toggle no-print">
                <button type="button" className={`view-btn ${mode === 'classe' ? 'view-btn--active' : ''}`} onClick={() => setMode('classe')}>
                  Par classe
                </button>
                <button type="button" className={`view-btn ${mode === 'enseignant' ? 'view-btn--active' : ''}`} onClick={() => setMode('enseignant')}>
                  Par enseignant
                </button>
              </div>
            )}
            {!isTeacher && mode === 'classe' && (
              <div className="edt-pagination">
                <button type="button" className="btn btn--ghost btn--sm" onClick={goPrev} disabled={classeIndex <= 0}>
                  ◀
                </button>
                <span className="edt-pagination-label">
                  {classes[classeIndex]
                    ? `${classes[classeIndex].nom} (${classes[classeIndex].niveau})`
                    : '—'}{' '}
                  • {classeIndex + 1}/{classes.length}
                </span>
                <button
                  type="button"
                  className="btn btn--ghost btn--sm"
                  onClick={goNext}
                  disabled={classeIndex >= classes.length - 1}
                >
                  ▶
                </button>
              </div>
            )}
            {!isTeacher && mode === 'enseignant' && (
              <Select value={enseignantId} onChange={(e) => setEnseignantId(e.target.value)} className="filter-select" aria-label="Choisir un enseignant">
                <option value="">Tous les enseignants</option>
                {enseignants.map((e) => (
                  <option key={e.id} value={e.id}>{e.prenom} {e.nom}</option>
                ))}
              </Select>
            )}
            <button type="button" className="btn btn--ghost no-print" onClick={() => window.print()}>
              🖨️ Imprimer
            </button>
          </>
        }
      />

      {!isTeacher && mode === 'enseignant' && !enseignantId && (
        <p className="muted">Choisissez un enseignant pour afficher son emploi du temps.</p>
      )}

      {loading ? (
        <div className="page-loader">
          <Spinner />
        </div>
      ) : (
        <div className="card edt-card">
          <div className="card-body">
            <div className="table-wrapper">
              <table className="table edt-table">
                <thead>
                  <tr>
                    <th className="edt-time-col">Heures</th>
                    {JOURS.map((j, i) => (
                      <th key={j}>{j}</th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {creneaux.map((creneau) => (
                    <tr key={creneau.id}>
                      <td className="edt-time">
                        {creneau.heure_debut}
                        <br />
                        {creneau.heure_fin}
                      </td>
                      {JOURS.map((_, jourIdx) => {
                        const jour = jourIdx + 1
                        const cell = cellFor(jour, creneau.id)
                        return (
                          <td key={jour} className="edt-cell">
                            {cell ? (
                              <button
                                type="button"
                                className="edt-course"
                                style={{ borderLeftColor: cell.matiere?.couleur || '#4f46e5' }}
                                onClick={() => openCell(jour, creneau)}
                                title={`${cell.matiere?.nom} — ${cell.enseignant ? cell.enseignant.prenom + ' ' + cell.enseignant.nom : ''}`}
                              >
                                <strong>{isTeacher ? cell.classe?.nom : cell.matiere?.nom}</strong>
                                <span className="edt-teacher">
                                  {isTeacher ? cell.matiere?.nom : cell.enseignant ? `${cell.enseignant.prenom} ${cell.enseignant.nom}` : ''}
                                </span>
                                {cell.salle && <span className="edt-room">{cell.salle}</span>}
                              </button>
                            ) : canEdit ? (
                              <button type="button" className="edt-empty" onClick={() => openCell(jour, creneau)} title="Ajouter un cours">
                                +
                              </button>
                            ) : (
                              <span className="edt-empty-static">—</span>
                            )}
                          </td>
                        )
                      })}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      <Modal
        open={modalOpen}
        title={editing ? 'Modifier le cours' : 'Programmer un cours'}
        onClose={() => setModalOpen(false)}
        footer={
          <>
            {editing && (
              <button type="button" className="btn btn--danger" onClick={() => { setDeleting(editing); setModalOpen(false) }} disabled={saving}>
                Supprimer
              </button>
            )}
            <button type="button" className="btn btn--ghost" onClick={() => setModalOpen(false)} disabled={saving}>
              Annuler
            </button>
            <button type="submit" form="edt-form" className="btn btn--primary" disabled={saving}>
              {saving ? 'Enregistrement…' : 'Enregistrer'}
            </button>
          </>
        }
      >
        <form id="edt-form" onSubmit={handleSubmit} noValidate>
          <Field label="Classe" htmlFor="classe_id" required error={formErrors.classe_id?.[0]}>
            <Select id="classe_id" name="classe_id" value={form.classe_id} onChange={handleChange}>
              <option value="">— Sélectionner —</option>
              {classes.map((c) => (
                <option key={c.id} value={c.id}>{c.nom} ({c.niveau})</option>
              ))}
            </Select>
          </Field>
          <div className="form-grid">
            <Field label="Matière" htmlFor="matiere_id" required error={formErrors.matiere_id?.[0]}>
              <Select id="matiere_id" name="matiere_id" value={form.matiere_id} onChange={handleChange}>
                <option value="">— Sélectionner —</option>
                {matieres.map((m) => (
                  <option key={m.id} value={m.id}>{m.nom}</option>
                ))}
              </Select>
            </Field>
            <Field label="Enseignant" htmlFor="enseignant_id" error={formErrors.enseignant_id?.[0]}>
              <Select id="enseignant_id" name="enseignant_id" value={form.enseignant_id} onChange={handleChange}>
                <option value="">— Aucun —</option>
                {enseignants.map((e) => (
                  <option key={e.id} value={e.id}>{e.prenom} {e.nom}</option>
                ))}
              </Select>
            </Field>
          </div>
          <div className="form-grid">
            <Field label="Jour" htmlFor="jour" required error={formErrors.jour?.[0]}>
              <Select id="jour" name="jour" value={form.jour} onChange={handleChange}>
                {JOURS.map((j, i) => (
                  <option key={i + 1} value={i + 1}>{j}</option>
                ))}
              </Select>
            </Field>
            <Field label="Créneau" htmlFor="creneau_id" required error={formErrors.creneau_id?.[0]}>
              <Select id="creneau_id" name="creneau_id" value={form.creneau_id} onChange={handleChange}>
                {creneaux.map((c) => (
                  <option key={c.id} value={c.id}>{c.heure_debut} — {c.heure_fin}</option>
                ))}
              </Select>
            </Field>
          </div>
          <Field label="Salle" htmlFor="salle" error={formErrors.salle?.[0]} hint="Optionnel">
            <Input id="salle" name="salle" value={form.salle} onChange={handleChange} placeholder="Ex : S12" error={formErrors.salle?.[0]} />
          </Field>
        </form>
      </Modal>

      {isTeacher && !loading && (
        <div className="card edt-card" style={{ marginTop: 16 }}>
          <div className="card-header">
            <h2>Mes cours</h2>
          </div>
          <div className="card-body">
            {entries.length === 0 ? (
              <p className="muted">Aucun cours programmé pour vous.</p>
            ) : (
              <div className="table-wrapper">
                <table className="table">
                  <thead>
                    <tr>
                      <th>Jour</th>
                      <th>Heure</th>
                      <th>Classe</th>
                      <th>Matière</th>
                      <th>Salle</th>
                    </tr>
                  </thead>
                  <tbody>
                    {[...entries]
                      .sort((a, b) => a.jour - b.jour || (a.creneau?.ordre ?? 0) - (b.creneau?.ordre ?? 0))
                      .map((cours) => (
                        <tr key={cours.id}>
                          <td>{JOURS[cours.jour - 1]}</td>
                          <td>{cours.creneau ? `${cours.creneau.heure_debut} – ${cours.creneau.heure_fin}` : '—'}</td>
                          <td className="cell-strong">{cours.classe?.nom || '—'}</td>
                          <td>{cours.matiere?.nom || '—'}</td>
                          <td>{cours.salle || '—'}</td>
                        </tr>
                      ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      )}

      <ConfirmDialog
        open={Boolean(deleting)}
        title="Supprimer le cours"
        message="Voulez-vous vraiment retirer ce cours de l'emploi du temps ?"
        loading={deleteLoading}
        onConfirm={confirmDelete}
        onCancel={() => setDeleting(null)}
      />
    </div>
  )
}
