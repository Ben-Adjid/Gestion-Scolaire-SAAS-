import { useCallback, useEffect, useState } from 'react'
import { absencesApi, classesApi, etudiantsApi } from '../api/endpoints'
import Modal from '../components/Modal'
import ConfirmDialog from '../components/ConfirmDialog'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Pagination from '../components/Pagination'
import Alert from '../components/Alert'
import { useToast } from '../components/Toast'
import { useAuth } from '../context/AuthContext'
import { Field, Input, Select } from '../components/Field'
import { formatDate } from '../utils/format'

const EMPTY_FORM = { etudiant_id: '', date: new Date().toISOString().slice(0, 10), type: 'absence', justifie: false, motif: '' }

export default function Absences() {
  const toast = useToast()
  const { user } = useAuth()
  const canEdit = ['admin', 'censeur', 'cpe'].includes(user?.role)

  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [stats, setStats] = useState(null)

  const [classeId, setClasseId] = useState('')
  const [typeFilter, setTypeFilter] = useState('')
  const [page, setPage] = useState(1)

  const [classes, setClasses] = useState([])
  const [etudiants, setEtudiants] = useState([])

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(EMPTY_FORM)
  const [formErrors, setFormErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const [deleting, setDeleting] = useState(null)
  const [deleteLoading, setDeleteLoading] = useState(false)

  useEffect(() => {
    classesApi.list({ per_page: 1000 }).then((res) => setClasses(res.data.data)).catch(() => {})
    etudiantsApi.list({ per_page: 1000 }).then((res) => setEtudiants(res.data.data)).catch(() => {})
    absencesApi.stats().then((res) => setStats(res.data)).catch(() => {})
  }, [])

  const fetchData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await absencesApi.list({
        classe_id: classeId || undefined,
        type: typeFilter || undefined,
        page,
      })
      setItems(res.data.data)
      setMeta(res.data)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [classeId, typeFilter, page])

  useEffect(() => {
    const timer = setTimeout(fetchData, 300)
    return () => clearTimeout(timer)
  }, [fetchData])

  const openCreate = () => {
    setEditing(null)
    setForm(EMPTY_FORM)
    setFormErrors({})
    setModalOpen(true)
  }

  const openEdit = (item) => {
    setEditing(item)
    setForm({
      etudiant_id: item.etudiant_id,
      date: item.date || '',
      type: item.type,
      justifie: item.justifie ? true : false,
      motif: item.motif || '',
    })
    setFormErrors({})
    setModalOpen(true)
  }

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target
    setForm((prev) => ({ ...prev, [name]: type === 'checkbox' ? checked : value }))
    setFormErrors((prev) => ({ ...prev, [name]: undefined }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setFormErrors({})
    try {
      if (editing) {
        await absencesApi.update(editing.id, form)
        toast.success('Absence mise à jour avec succès.')
      } else {
        await absencesApi.create(form)
        toast.success('Absence enregistrée avec succès.')
      }
      setModalOpen(false)
      fetchData()
      absencesApi.stats().then((res) => setStats(res.data)).catch(() => {})
    } catch (err) {
      setFormErrors(err.errors || {})
    } finally {
      setSaving(false)
    }
  }

  const confirmDelete = async () => {
    setDeleteLoading(true)
    try {
      await absencesApi.remove(deleting.id)
      toast.success('Absence supprimée avec succès.')
      setDeleting(null)
      fetchData()
      absencesApi.stats().then((res) => setStats(res.data)).catch(() => {})
    } catch (err) {
      toast.error(err.message)
    } finally {
      setDeleteLoading(false)
    }
  }

  return (
    <div>
      <div className="toolbar">
        <Select value={classeId} onChange={(e) => { setClasseId(e.target.value); setPage(1) }} className="filter-select" aria-label="Filtrer par classe">
          <option value="">Toutes les classes</option>
          {classes.map((c) => (
            <option key={c.id} value={c.id}>{c.nom}</option>
          ))}
        </Select>
        <Select value={typeFilter} onChange={(e) => { setTypeFilter(e.target.value); setPage(1) }} className="filter-select" aria-label="Filtrer par type">
          <option value="">Absences + Retards</option>
          <option value="absence">Absences</option>
          <option value="retard">Retards</option>
        </Select>
        {canEdit && (
          <button type="button" className="btn btn--primary" onClick={openCreate}>
            + Signaler une absence
          </button>
        )}
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
            <EmptyState title="Aucune absence" description="Aucune absence ou retard enregistré." />
          ) : (
            <div className="table-wrapper">
              <table className="table">
                <thead>
                  <tr>
                    <th>Élève</th>
                    <th>Classe</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Justifiée</th>
                    <th>Motif</th>
                    <th className="th-actions">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((item) => (
                    <tr key={item.id}>
                      <td className="cell-strong">{item.etudiant?.prenom} {item.etudiant?.nom}</td>
                      <td><span className="badge">{item.etudiant?.classe?.nom}</span></td>
                      <td>{formatDate(item.date)}</td>
                      <td>
                        <span className={`badge ${item.type === 'absence' ? 'badge--danger' : 'badge--amber'}`}>
                          {item.type === 'absence' ? 'Absence' : 'Retard'}
                        </span>
                      </td>
                      <td>
                        {item.justifie ? (
                          <span className="badge badge--success">Justifiée</span>
                        ) : (
                          <span className="badge badge--neutral">Non justifiée</span>
                        )}
                      </td>
                      <td className="cell-muted">{item.motif || '—'}</td>
                      <td className="cell-actions">
                        {canEdit && (
                          <>
                            <button type="button" className="icon-btn" title="Modifier" onClick={() => openEdit(item)}>✏️</button>
                            <button type="button" className="icon-btn" title="Supprimer" onClick={() => setDeleting(item)}>🗑️</button>
                          </>
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

      <Modal
        open={modalOpen}
        title={editing ? "Modifier l'absence" : 'Signaler une absence'}
        onClose={() => setModalOpen(false)}
        footer={
          <>
            <button type="button" className="btn btn--ghost" onClick={() => setModalOpen(false)} disabled={saving}>Annuler</button>
            <button type="submit" form="absence-form" className="btn btn--primary" disabled={saving}>
              {saving ? 'Enregistrement…' : 'Enregistrer'}
            </button>
          </>
        }
      >
        <form id="absence-form" onSubmit={handleSubmit} noValidate>
          <Field label="Élève" htmlFor="etudiant_id" required error={formErrors.etudiant_id?.[0]}>
            <Select id="etudiant_id" name="etudiant_id" value={form.etudiant_id} onChange={handleChange}>
              <option value="">— Sélectionner —</option>
              {etudiants.map((e) => (
                <option key={e.id} value={e.id}>{e.prenom} {e.nom}</option>
              ))}
            </Select>
          </Field>
          <div className="form-grid">
            <Field label="Date" htmlFor="date" required error={formErrors.date?.[0]}>
              <Input id="date" type="date" name="date" value={form.date} onChange={handleChange} error={formErrors.date?.[0]} />
            </Field>
            <Field label="Type" htmlFor="type" required error={formErrors.type?.[0]}>
              <Select id="type" name="type" value={form.type} onChange={handleChange}>
                <option value="absence">Absence</option>
                <option value="retard">Retard</option>
              </Select>
            </Field>
          </div>
          <Field label="Motif" htmlFor="motif" error={formErrors.motif?.[0]} hint="Optionnel">
            <Input id="motif" name="motif" value={form.motif} onChange={handleChange} placeholder="Ex : Maladie" error={formErrors.motif?.[0]} />
          </Field>
          <label className="checkbox-label">
            <input type="checkbox" name="justifie" checked={form.justifie} onChange={handleChange} />
            Absence justifiée
          </label>
        </form>
      </Modal>

      <ConfirmDialog
        open={Boolean(deleting)}
        title="Supprimer l'absence"
        message="Voulez-vous vraiment supprimer cette absence ?"
        loading={deleteLoading}
        onConfirm={confirmDelete}
        onCancel={() => setDeleting(null)}
      />
    </div>
  )
}
