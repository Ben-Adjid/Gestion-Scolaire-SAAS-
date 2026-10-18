import { useCallback, useEffect, useState } from 'react'
import { classesApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Modal from '../components/Modal'
import ConfirmDialog from '../components/ConfirmDialog'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Pagination from '../components/Pagination'
import Alert from '../components/Alert'
import { useToast } from '../components/Toast'
import { Field, Input, Textarea } from '../components/Field'

const EMPTY_FORM = { nom: '', niveau: '', description: '' }

export default function Classes() {
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
      const res = await classesApi.list({ search: search || undefined, page })
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
      niveau: item.niveau,
      description: item.description || '',
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
        await classesApi.update(editing.id, form)
        toast.success('Classe mise à jour avec succès.')
      } else {
        await classesApi.create(form)
        toast.success('Classe créée avec succès.')
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
      await classesApi.remove(deleting.id)
      toast.success('Classe supprimée avec succès.')
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
        title="Classes"
        subtitle="Gérez les classes et niveaux de l'établissement."
        search={search}
        onSearch={setSearch}
        searchPlaceholder="Rechercher une classe…"
        actionLabel="Nouvelle classe"
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
              title="Aucune classe"
              description={
                search
                  ? 'Aucune classe ne correspond à votre recherche.'
                  : 'Commencez par ajouter votre première classe.'
              }
              action={
                !search && (
                  <button type="button" className="btn btn--primary" onClick={openCreate}>
                    + Nouvelle classe
                  </button>
                )
              }
            />
          ) : (
            <div className="table-wrapper">
              <table className="table">
                <thead>
                  <tr>
                    <th>Nom</th>
                    <th>Niveau</th>
                    <th>Élèves</th>
                    <th>Description</th>
                    <th className="th-actions">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((item) => (
                    <tr key={item.id}>
                      <td className="cell-strong">
                        {item.nom}
                        {['3ème', 'Terminale'].includes(item.niveau) && (
                          <span className="badge badge--exam">🎓 Examen</span>
                        )}
                      </td>
                      <td>
                        <span className="badge">{item.niveau}</span>
                      </td>
                      <td>{item.etudiants_count}</td>
                      <td className="cell-muted">{item.description || '—'}</td>
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
        title={editing ? 'Modifier la classe' : 'Nouvelle classe'}
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
              form="classe-form"
              className="btn btn--primary"
              disabled={saving}
            >
              {saving ? 'Enregistrement…' : editing ? 'Enregistrer' : 'Créer'}
            </button>
          </>
        }
      >
        <form id="classe-form" onSubmit={handleSubmit} noValidate>
          <div className="form-grid">
            <Field label="Nom de la classe" htmlFor="nom" required error={formErrors.nom?.[0]}>
              <Input
                id="nom"
                name="nom"
                value={form.nom}
                onChange={handleChange}
                placeholder="Ex : Terminale A"
                error={formErrors.nom?.[0]}
              />
            </Field>
            <Field label="Niveau" htmlFor="niveau" required error={formErrors.niveau?.[0]}>
              <Input
                id="niveau"
                name="niveau"
                value={form.niveau}
                onChange={handleChange}
                placeholder="Ex : Terminale"
                error={formErrors.niveau?.[0]}
              />
            </Field>
          </div>
          <Field
            label="Description"
            htmlFor="description"
            error={formErrors.description?.[0]}
            hint="Optionnel"
          >
            <Textarea
              id="description"
              name="description"
              value={form.description}
              onChange={handleChange}
              rows={3}
              placeholder="Description de la classe…"
              error={formErrors.description?.[0]}
            />
          </Field>
        </form>
      </Modal>

      <ConfirmDialog
        open={Boolean(deleting)}
        title="Supprimer la classe"
        message={
          deleting
            ? `Voulez-vous vraiment supprimer la classe « ${deleting.nom} » ? Les élèves qui y sont rattachés seront conservés sans classe.`
            : ''
        }
        loading={deleteLoading}
        onConfirm={confirmDelete}
        onCancel={() => setDeleting(null)}
      />
    </div>
  )
}
