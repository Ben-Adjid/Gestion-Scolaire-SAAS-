import { useCallback, useEffect, useState } from 'react'
import { matieresApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Modal from '../components/Modal'
import ConfirmDialog from '../components/ConfirmDialog'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Pagination from '../components/Pagination'
import Alert from '../components/Alert'
import { useToast } from '../components/Toast'
import { Field, Input } from '../components/Field'

const EMPTY_FORM = { nom: '', coefficient: 1, couleur: '#4f46e5' }

const PALETTE = [
  '#4f46e5', '#2563eb', '#0891b2', '#0f766e', '#16a34a', '#7c3aed',
  '#db2777', '#e11d48', '#d97706', '#ea580c', '#64748b', '#0f172a',
]

export default function Matieres() {
  const toast = useToast()
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(EMPTY_FORM)
  const [formErrors, setFormErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const [deleting, setDeleting] = useState(null)
  const [deleteLoading, setDeleteLoading] = useState(false)

  const fetchData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await matieresApi.list({ search: search || undefined, page })
      setItems(res.data.data)
      setMeta(res.data)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [search, page])

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
      nom: item.nom,
      coefficient: item.coefficient,
      couleur: item.couleur || '#4f46e5',
    })
    setFormErrors({})
    setModalOpen(true)
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
    try {
      if (editing) {
        await matieresApi.update(editing.id, form)
        toast.success('Matière mise à jour avec succès.')
      } else {
        await matieresApi.create(form)
        toast.success('Matière créée avec succès.')
      }
      setModalOpen(false)
      fetchData()
    } catch (err) {
      setFormErrors(err.errors || {})
    } finally {
      setSaving(false)
    }
  }

  const confirmDelete = async () => {
    setDeleteLoading(true)
    try {
      await matieresApi.remove(deleting.id)
      toast.success('Matière supprimée avec succès.')
      setDeleting(null)
      fetchData()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setDeleteLoading(false)
    }
  }

  return (
    <div>
      <PageHeader
        title="Matières"
        subtitle="Gérez les matières et leurs coefficients."
        search={search}
        onSearch={setSearch}
        searchPlaceholder="Rechercher une matière…"
        actionLabel="Nouvelle matière"
        onAction={openCreate}
      />

      {error && <Alert>{error}</Alert>}

      <div className="card">
        <div className="card-body">
          {loading ? (
            <div className="page-loader">
              <Spinner />
            </div>
          ) : items.length === 0 ? (
            <EmptyState
              title="Aucune matière"
              description="Ajoutez vos matières pour pouvoir saisir des notes."
              action={
                <button type="button" className="btn btn--primary" onClick={openCreate}>
                  + Nouvelle matière
                </button>
              }
            />
          ) : (
            <div className="table-wrapper">
              <table className="table">
                <thead>
                  <tr>
                    <th>Matière</th>
                    <th>Coefficient</th>
                    <th>Couleur</th>
                    <th className="th-actions">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((item) => (
                    <tr key={item.id}>
                      <td>
                        <span className="matiere-cell">
                          <span
                            className="matiere-dot"
                            style={{ background: item.couleur || '#4f46e5' }}
                          />
                          <span className="cell-strong">{item.nom}</span>
                        </span>
                      </td>
                      <td>
                        <span className="badge badge--neutral">Coef. {item.coefficient}</span>
                      </td>
                      <td>
                        <span
                          className="color-swatch"
                          style={{ background: item.couleur || '#4f46e5' }}
                        >
                          {item.couleur}
                        </span>
                      </td>
                      <td className="cell-actions">
                        <button
                          type="button"
                          className="icon-btn"
                          title="Modifier"
                          onClick={() => openEdit(item)}
                        >
                          ✏️
                        </button>
                        <button
                          type="button"
                          className="icon-btn"
                          title="Supprimer"
                          onClick={() => setDeleting(item)}
                        >
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

      <Modal
        open={modalOpen}
        title={editing ? 'Modifier la matière' : 'Nouvelle matière'}
        onClose={() => setModalOpen(false)}
        footer={
          <>
            <button
              type="button"
              className="btn btn--ghost"
              onClick={() => setModalOpen(false)}
              disabled={saving}
            >
              Annuler
            </button>
            <button
              type="submit"
              form="matiere-form"
              className="btn btn--primary"
              disabled={saving}
            >
              {saving ? 'Enregistrement…' : editing ? 'Enregistrer' : 'Créer'}
            </button>
          </>
        }
      >
        <form id="matiere-form" onSubmit={handleSubmit} noValidate>
          <Field label="Nom de la matière" htmlFor="nom" required error={formErrors.nom?.[0]}>
            <Input
              id="nom"
              name="nom"
              value={form.nom}
              onChange={handleChange}
              placeholder="Ex : Mathématiques"
              error={formErrors.nom?.[0]}
            />
          </Field>
          <Field
            label="Coefficient"
            htmlFor="coefficient"
            required
            error={formErrors.coefficient?.[0]}
          >
            <Input
              id="coefficient"
              type="number"
              min="1"
              max="20"
              name="coefficient"
              value={form.coefficient}
              onChange={handleChange}
              error={formErrors.coefficient?.[0]}
            />
          </Field>
          <Field label="Couleur" htmlFor="couleur">
            <div className="palette">
              {PALETTE.map((color) => (
                <button
                  key={color}
                  type="button"
                  className={`palette-dot ${form.couleur === color ? 'palette-dot--active' : ''}`}
                  style={{ background: color }}
                  onClick={() => setForm((prev) => ({ ...prev, couleur: color }))}
                  aria-label={`Choisir ${color}`}
                />
              ))}
            </div>
          </Field>
        </form>
      </Modal>

      <ConfirmDialog
        open={Boolean(deleting)}
        title="Supprimer la matière"
        message={
          deleting
            ? `Voulez-vous vraiment supprimer la matière « ${deleting.nom} » ? Toutes les notes associées seront supprimées.`
            : ''
        }
        loading={deleteLoading}
        onConfirm={confirmDelete}
        onCancel={() => setDeleting(null)}
      />
    </div>
  )
}
