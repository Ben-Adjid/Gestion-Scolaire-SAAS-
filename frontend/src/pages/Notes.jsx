import { useCallback, useEffect, useState } from 'react'
import { classesApi, etudiantsApi, matieresApi, notesApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Modal from '../components/Modal'
import ConfirmDialog from '../components/ConfirmDialog'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Pagination from '../components/Pagination'
import Alert from '../components/Alert'
import { useToast } from '../components/Toast'
import { useAuth } from '../context/AuthContext'
import { Field, Input, Select } from '../components/Field'
import { downloadCsv } from '../utils/csv'
import { downloadTablePdf } from '../utils/pdf'
import { noteClass } from '../utils/format'
import { TRIMESTRES, TYPES_NOTE } from '../utils/constants'

export default function Notes() {
  const toast = useToast()
  const { user } = useAuth()
  const isAdmin = user?.role === 'admin'
  const [tab, setTab] = useState('saisie')

  const [classes, setClasses] = useState([])
  const [matieres, setMatieres] = useState([])

  // Saisie groupée
  const [classeId, setClasseId] = useState('')
  const [matiereId, setMatiereId] = useState('')
  const [trimestre, setTrimestre] = useState(1)
  const [type, setType] = useState('devoir')
  const [eleves, setEleves] = useState([])
  const [notesMap, setNotesMap] = useState({})
  const [locked, setLocked] = useState({})
  const [loadingEleves, setLoadingEleves] = useState(false)
  const [savingBulk, setSavingBulk] = useState(false)

  // Liste
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [loading, setLoading] = useState(false)
  const [listMatiere, setListMatiere] = useState('')
  const [listTrimestre, setListTrimestre] = useState('')
  const [page, setPage] = useState(1)

  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState({ valeur: '', appreciation: '' })
  const [formErrors, setFormErrors] = useState({})
  const [saving, setSaving] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)

  const [deleting, setDeleting] = useState(null)
  const [deleteLoading, setDeleteLoading] = useState(false)

  useEffect(() => {
    classesApi.list({ per_page: 1000 }).then((res) => setClasses(res.data.data)).catch(() => {})
    matieresApi.list({ per_page: 1000 }).then((res) => setMatieres(res.data.data)).catch(() => {})
  }, [])

  const refresh = useCallback(async () => {
    if (!classeId) {
      setEleves([])
      setNotesMap({})
      setLocked({})
      return
    }
    setLoadingEleves(true)
    try {
      const res = await etudiantsApi.list({ classe_id: classeId, per_page: 1000 })
      setEleves(res.data.data)

      let notesData = []
      if (matiereId) {
        const nres = await notesApi.list({ matiere_id: matiereId, trimestre, per_page: 1000 })
        notesData = nres.data.data.filter((n) => n.type === type)
      }
      const map = {}
      const lockedMap = {}
      notesData.forEach((n) => {
        map[n.etudiant_id] = n.valeur
        if (n.statut === 'valide') lockedMap[n.etudiant_id] = true
      })
      setNotesMap(map)
      setLocked(lockedMap)
    } catch (err) {
      toast.error(err.message)
    } finally {
      setLoadingEleves(false)
    }
  }, [classeId, matiereId, trimestre, type, toast])

  useEffect(() => {
    refresh()
  }, [refresh])

  const fetchList = useCallback(async () => {
    setLoading(true)
    try {
      const res = await notesApi.list({
        matiere_id: listMatiere || undefined,
        trimestre: listTrimestre || undefined,
        page,
      })
      setItems(res.data.data)
      setMeta(res.data)
    } catch (err) {
      toast.error(err.message)
    } finally {
      setLoading(false)
    }
  }, [listMatiere, listTrimestre, page, toast])

  useEffect(() => {
    if (tab === 'liste') fetchList()
  }, [tab, fetchList])

  const handleNoteChange = (etudiantId, value) => {
    setNotesMap((prev) => ({ ...prev, [etudiantId]: value }))
  }

  const saveBulk = async (valider) => {
    const notes = eleves
      .filter((e) => notesMap[e.id] !== undefined && notesMap[e.id] !== '' && (isAdmin || !locked[e.id]))
      .map((e) => ({ etudiant_id: e.id, valeur: parseFloat(notesMap[e.id]) }))

    if (!matiereId) {
      toast.error('Veuillez choisir une matière.')
      return
    }
    if (notes.length === 0) {
      toast.error(valider ? 'Aucune note à valider.' : 'Aucune note saisie.')
      return
    }

    setSavingBulk(true)
    try {
      await notesApi.bulk({ matiere_id: matiereId, trimestre, type, valider, notes })
      toast.success(valider ? 'Notes validées et verrouillées.' : 'Notes enregistrées en brouillon.')
      refresh()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setSavingBulk(false)
    }
  }

  const openEdit = (item) => {
    setEditing(item)
    setForm({ valeur: item.valeur, appreciation: item.appreciation || '' })
    setFormErrors({})
    setModalOpen(true)
  }

  const handleFormChange = (e) => {
    const { name, value } = e.target
    setForm((prev) => ({ ...prev, [name]: value }))
    setFormErrors((prev) => ({ ...prev, [name]: undefined }))
  }

  const handleUpdate = async (e) => {
    e.preventDefault()
    setSaving(true)
    setFormErrors({})
    try {
      await notesApi.update(editing.id, {
        etudiant_id: editing.etudiant_id,
        matiere_id: editing.matiere_id,
        trimestre: editing.trimestre,
        type: editing.type,
        valeur: parseFloat(form.valeur),
        appreciation: form.appreciation,
      })
      toast.success('Note mise à jour avec succès.')
      setModalOpen(false)
      fetchList()
    } catch (err) {
      setFormErrors(err.errors || {})
    } finally {
      setSaving(false)
    }
  }

  const handleDownload = () => {
    const header = ['Élève', 'Matière', 'Trimestre', 'Type', 'Note', 'Statut', 'Appréciation']
    const rows = items.map((n) => [
      `${n.etudiant?.prenom} ${n.etudiant?.nom}`,
      n.matiere?.nom,
      `T${n.trimestre}`,
      n.type,
      parseFloat(n.valeur).toFixed(2),
      n.statut === 'valide' ? 'Validée' : 'Brouillon',
      n.appreciation || '',
    ])
    downloadCsv(`notes-${new Date().toISOString().slice(0, 10)}.csv`, [header, ...rows])
  }

  const handleDownloadPdf = () => {
    const columns = ['Élève', 'Matière', 'Trimestre', 'Type', 'Note', 'Statut']
    const rows = items.map((n) => [
      `${n.etudiant?.prenom} ${n.etudiant?.nom}`,
      n.matiere?.nom,
      `T${n.trimestre}`,
      n.type,
      parseFloat(n.valeur).toFixed(2),
      n.statut === 'valide' ? 'Validée' : 'Brouillon',
    ])
    downloadTablePdf({
      title: 'Liste des notes',
      subtitle: `Généré le ${new Date().toLocaleDateString('fr-FR')}`,
      columns,
      rows,
      filename: `notes-${new Date().toISOString().slice(0, 10)}.pdf`,
    })
  }

  const confirmDelete = async () => {
    setDeleteLoading(true)
    try {
      await notesApi.remove(deleting.id)
      toast.success('Note supprimée avec succès.')
      setDeleting(null)
      fetchList()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setDeleteLoading(false)
    }
  }

  const canModify = (item) => isAdmin || item.statut !== 'valide'

  return (
    <div>
      <PageHeader title="Notes" subtitle="Saisissez, validez et consultez les notes des élèves." />

      <div className="info-panel">
        <strong>Règles de saisie :</strong> un enseignant ne saisit que les notes de{' '}
        <strong>ses matières</strong> et de <strong>ses classes</strong>. La <strong>moyenne</strong>{' '}
        d'une matière = moyenne de ses notes ; la <strong>moyenne générale</strong> = Σ (moyenne ×
        coefficient) / Σ coefficients (calcul automatique). Une note <strong>validée</strong> est
        verrouillée (seul le proviseur peut la corriger).
      </div>

      <div className="tabs">
        <button type="button" className={`tab ${tab === 'saisie' ? 'tab--active' : ''}`} onClick={() => setTab('saisie')}>
          Saisie groupée
        </button>
        <button type="button" className={`tab ${tab === 'liste' ? 'tab--active' : ''}`} onClick={() => setTab('liste')}>
          Toutes les notes
        </button>
      </div>

      {tab === 'saisie' ? (
        <div className="card">
          <div className="card-body">
            <div className="filters-row">
              <Select value={classeId} onChange={(e) => { setClasseId(e.target.value); setNotesMap({}); setLocked({}) }} aria-label="Choisir une classe">
                <option value="">— Choisir une classe —</option>
                {classes.map((c) => (
                  <option key={c.id} value={c.id}>{c.nom} ({c.niveau})</option>
                ))}
              </Select>
              <Select value={matiereId} onChange={(e) => setMatiereId(e.target.value)} aria-label="Choisir une matière">
                <option value="">— Choisir une matière —</option>
                {matieres.map((m) => (
                  <option key={m.id} value={m.id}>{m.nom}</option>
                ))}
              </Select>
              <Select value={trimestre} onChange={(e) => setTrimestre(Number(e.target.value))} aria-label="Choisir le trimestre">
                {TRIMESTRES.map((t) => (
                  <option key={t.value} value={t.value}>{t.label}</option>
                ))}
              </Select>
              <Select value={type} onChange={(e) => setType(e.target.value)} aria-label="Choisir le type">
                {TYPES_NOTE.map((t) => (
                  <option key={t} value={t}>{t}</option>
                ))}
              </Select>
            </div>

            <div className="note-rule-hint">
              🔒 Une fois validées, les notes sont <strong>verrouillées</strong> : seul le proviseur peut les modifier ou les supprimer.
            </div>

            {!classeId ? (
              <EmptyState title="Choisissez une classe" description="Sélectionnez une classe pour afficher ses élèves." />
            ) : loadingEleves ? (
              <div className="page-loader"><Spinner /></div>
            ) : eleves.length === 0 ? (
              <EmptyState title="Aucun élève" description="Cette classe ne contient aucun élève." />
            ) : (
              <>
                <div className="table-wrapper">
                  <table className="table">
                    <thead>
                      <tr>
                        <th>Élève</th>
                        <th>Matricule</th>
                        <th style={{ width: 160 }}>Note /20</th>
                        <th style={{ width: 100 }}>Statut</th>
                      </tr>
                    </thead>
                    <tbody>
                      {eleves.map((e) => (
                        <tr key={e.id}>
                          <td className="cell-strong">{e.prenom} {e.nom}</td>
                          <td className="cell-muted">{e.matricule || '—'}</td>
                          <td>
                            {locked[e.id] && !isAdmin ? (
                              <input
                                type="number"
                                className="input note-input note-locked"
                                value={notesMap[e.id] ?? ''}
                                disabled
                              />
                            ) : (
                              <input
                                type="number"
                                min="0"
                                max="20"
                                step="0.25"
                                className={`input note-input ${noteClass(parseFloat(notesMap[e.id]))}`}
                                value={notesMap[e.id] ?? ''}
                                onChange={(ev) => handleNoteChange(e.id, ev.target.value)}
                                placeholder="—"
                              />
                            )}
                          </td>
                          <td>
                            {locked[e.id] ? (
                              <span className="badge badge--success">🔒 Validée</span>
                            ) : notesMap[e.id] !== undefined && notesMap[e.id] !== '' ? (
                              <span className="badge badge--amber">Brouillon</span>
                            ) : (
                              <span className="muted">—</span>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <div className="bulk-actions">
                  <button type="button" className="btn btn--ghost" onClick={() => saveBulk(false)} disabled={savingBulk || !matiereId}>
                    {savingBulk ? 'Enregistrement…' : 'Enregistrer (brouillon)'}
                  </button>
                  <button type="button" className="btn btn--primary" onClick={() => saveBulk(true)} disabled={savingBulk || !matiereId}>
                    🔒 Valider les notes
                  </button>
                </div>
              </>
            )}
          </div>
        </div>
      ) : (
        <div className="card">
          <div className="card-body">
            <div className="filters-row">
              <Select value={listMatiere} onChange={(e) => { setListMatiere(e.target.value); setPage(1) }} aria-label="Filtrer par matière">
                <option value="">Toutes les matières</option>
                {matieres.map((m) => (
                  <option key={m.id} value={m.id}>{m.nom}</option>
                ))}
              </Select>
              <Select value={listTrimestre} onChange={(e) => { setListTrimestre(e.target.value); setPage(1) }} aria-label="Filtrer par trimestre">
                <option value="">Tous les trimestres</option>
                {TRIMESTRES.map((t) => (
                  <option key={t.value} value={t.value}>{t.label}</option>
                ))}
              </Select>
              <button type="button" className="btn btn--ghost btn--sm" onClick={handleDownload}>
                ⬇ Télécharger CSV
              </button>
              <button type="button" className="btn btn--ghost btn--sm" onClick={handleDownloadPdf}>
                📄 Télécharger PDF
              </button>
            </div>

            {loading ? (
              <div className="page-loader"><Spinner /></div>
            ) : items.length === 0 ? (
              <EmptyState title="Aucune note" description="Aucune note ne correspond à vos critères." />
            ) : (
              <div className="table-wrapper">
                <table className="table">
                  <thead>
                    <tr>
                      <th>Élève</th>
                      <th>Matière</th>
                      <th>Trimestre</th>
                      <th>Type</th>
                      <th>Note</th>
                      <th>Statut</th>
                      <th className="th-actions">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {items.map((item) => (
                      <tr key={item.id}>
                        <td className="cell-strong">{item.etudiant?.prenom} {item.etudiant?.nom}</td>
                        <td>
                          <span className="matiere-cell">
                            <span className="matiere-dot" style={{ background: item.matiere?.couleur || '#4f46e5' }} />
                            {item.matiere?.nom}
                          </span>
                        </td>
                        <td>T{item.trimestre}</td>
                        <td className="cell-muted">{item.type}</td>
                        <td>
                          <span className={`note-badge ${noteClass(parseFloat(item.valeur))}`}>
                            {parseFloat(item.valeur).toFixed(2)}
                          </span>
                        </td>
                        <td>
                          {item.statut === 'valide' ? (
                            <span className="badge badge--success">🔒 Validée</span>
                          ) : (
                            <span className="badge badge--amber">Brouillon</span>
                          )}
                        </td>
                        <td className="cell-actions">
                          {canModify(item) ? (
                            <>
                              <button type="button" className="icon-btn" title="Modifier" onClick={() => openEdit(item)}>✏️</button>
                              <button type="button" className="icon-btn" title="Supprimer" onClick={() => setDeleting(item)}>🗑️</button>
                            </>
                          ) : (
                            <span className="muted" title="Note verrouillée">🔒</span>
                          )}
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
      )}

      <Modal
        open={modalOpen}
        title="Modifier la note"
        onClose={() => setModalOpen(false)}
        size="sm"
        footer={
          <>
            <button type="button" className="btn btn--ghost" onClick={() => setModalOpen(false)} disabled={saving}>Annuler</button>
            <button type="submit" form="note-form" className="btn btn--primary" disabled={saving}>Enregistrer</button>
          </>
        }
      >
        <form id="note-form" onSubmit={handleUpdate} noValidate>
          <Field label="Note /20" htmlFor="valeur" required error={formErrors.valeur?.[0]}>
            <Input id="valeur" type="number" min="0" max="20" step="0.25" name="valeur" value={form.valeur} onChange={handleFormChange} error={formErrors.valeur?.[0]} />
          </Field>
          <Field label="Appréciation" htmlFor="appreciation" error={formErrors.appreciation?.[0]}>
            <Input id="appreciation" name="appreciation" value={form.appreciation} onChange={handleFormChange} placeholder="Facultatif" error={formErrors.appreciation?.[0]} />
          </Field>
        </form>
      </Modal>

      <ConfirmDialog
        open={Boolean(deleting)}
        title="Supprimer la note"
        message="Voulez-vous vraiment supprimer cette note ?"
        loading={deleteLoading}
        onConfirm={confirmDelete}
        onCancel={() => setDeleting(null)}
      />
    </div>
  )
}
