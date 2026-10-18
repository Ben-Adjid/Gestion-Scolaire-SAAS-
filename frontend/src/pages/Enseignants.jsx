import { useCallback, useEffect, useState } from 'react'
import { enseignantsApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Modal from '../components/Modal'
import ConfirmDialog from '../components/ConfirmDialog'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Pagination from '../components/Pagination'
import Alert from '../components/Alert'
import { useToast } from '../components/Toast'
import { Field, Input } from '../components/Field'

const EMPTY_FORM = { nom: '', prenom: '', email: '', telephone: '', specialite: '' }

export default function Enseignants() {
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
      const res = await enseignantsApi.list({ search: search || undefined, page })
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
      prenom: item.prenom,
      email: item.email,
      telephone: item.telephone || '',
      specialite: item.specialite || '',
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
        await enseignantsApi.update(editing.id, form)
        toast.success('Enseignant mis à jour avec succès.')
      } else {
        await enseignantsApi.create(form)
        toast.success('Enseignant créé avec succès.')
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
      await enseignantsApi.remove(deleting.id)
      toast.success('Enseignant supprimé avec succès.')
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
        title="Enseignants"
        subtitle="Gérez le corps enseignant de l'établissement."
        search={search}
        onSearch={setSearch}
        searchPlaceholder="Rechercher un enseignant…"
        actionLabel="Nouvel enseignant"
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
              title="Aucun enseignant"
              description={
                search
                  ? 'Aucun enseignant ne correspond à votre recherche.'
                  : 'Commencez par ajouter votre premier enseignant.'
              }
              action={
                !search && (
                  <button type="button" className="btn btn--primary" onClick={openCreate}>
                    + Nouvel enseignant
                  </button>
                )
              }
            />
          ) : (
            <div className="table-wrapper">
              <table className="table">
                <thead>
                  <tr>
                    <th>Nom complet</th>
                    <th>Email</th>
                    <th>Spécialité</th>
                    <th>Code pointage</th>
                    <th>Téléphone</th>
                    <th className="th-actions">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((item) => (
                    <tr key={item.id}>
                      <td className="cell-strong">
                        {item.prenom} {item.nom}
                      </td>
                      <td>{item.email}</td>
                      <td>
                        {item.specialite ? <span className="badge">{item.specialite}</span> : '—'}
                      </td>
                      <td>
                        {item.code_pointe ? (
                          <span className="badge badge--neutral">{item.code_pointe}</span>
                        ) : (
                          '—'
                        )}
                      </td>
                      <td>{item.telephone || '—'}</td>
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
        title={editing ? "Modifier l'enseignant" : 'Nouvel enseignant'}
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
              form="enseignant-form"
              className="btn btn--primary"
              disabled={saving}
            >
              {saving ? 'Enregistrement…' : editing ? 'Enregistrer' : 'Créer'}
            </button>
          </>
        }
      >
        <form id="enseignant-form" onSubmit={handleSubmit} noValidate>
          <div className="form-grid">
            <Field label="Prénom" htmlFor="prenom" required error={formErrors.prenom?.[0]}>
              <Input
                id="prenom"
                name="prenom"
                value={form.prenom}
                onChange={handleChange}
                placeholder="Ex : Awa"
                error={formErrors.prenom?.[0]}
              />
            </Field>
            <Field label="Nom" htmlFor="nom" required error={formErrors.nom?.[0]}>
              <Input
                id="nom"
                name="nom"
                value={form.nom}
                onChange={handleChange}
                placeholder="Ex : Diop"
                error={formErrors.nom?.[0]}
              />
            </Field>
          </div>
          <Field label="Adresse e-mail" htmlFor="email" required error={formErrors.email?.[0]}>
            <Input
              id="email"
              type="email"
              name="email"
              value={form.email}
              onChange={handleChange}
              placeholder="enseignant@ecole.com"
              error={formErrors.email?.[0]}
            />
          </Field>
          <div className="form-grid">
            <Field
              label="Spécialité"
              htmlFor="specialite"
              error={formErrors.specialite?.[0]}
              hint="Optionnel"
            >
              <Input
                id="specialite"
                name="specialite"
                value={form.specialite}
                onChange={handleChange}
                placeholder="Ex : Mathématiques"
                error={formErrors.specialite?.[0]}
              />
            </Field>
            <Field
              label="Téléphone"
              htmlFor="telephone"
              error={formErrors.telephone?.[0]}
              hint="Optionnel"
            >
              <Input
                id="telephone"
                name="telephone"
                value={form.telephone}
                onChange={handleChange}
                placeholder="Ex : +221 77 000 00 00"
                error={formErrors.telephone?.[0]}
              />
            </Field>
          </div>
        </form>
      </Modal>

      <ConfirmDialog
        open={Boolean(deleting)}
        title="Supprimer l'enseignant"
        message={
          deleting
            ? `Voulez-vous vraiment supprimer l'enseignant « ${deleting.prenom} ${deleting.nom} » ?`
            : ''
        }
        loading={deleteLoading}
        onConfirm={confirmDelete}
        onCancel={() => setDeleting(null)}
      />
    </div>
  )
}
