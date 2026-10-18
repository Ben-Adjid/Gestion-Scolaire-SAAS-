import { useCallback, useEffect, useState } from 'react'
import { enseignantsApi, usersApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Modal from '../components/Modal'
import ConfirmDialog from '../components/ConfirmDialog'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Pagination from '../components/Pagination'
import Alert from '../components/Alert'
import { useToast } from '../components/Toast'
import { Field, Input, Select } from '../components/Field'
import { ROLE_LABELS } from '../utils/constants'

const ROLES = Object.entries(ROLE_LABELS)
  .filter(([value]) => value !== 'super_admin')
  .map(([value, label]) => ({ value, label }))

const EMPTY_FORM = { name: '', email: '', role: 'enseignant', password: '', enseignant_id: '' }

function roleLabel(value) {
  return ROLES.find((r) => r.value === value)?.label || value
}

function roleBadge(value) {
  switch (value) {
    case 'admin':
      return 'badge--danger'
    case 'censeur':
      return 'badge--amber'
    default:
      return 'badge--success'
  }
}

export default function Utilisateurs() {
  const toast = useToast()
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [enseignants, setEnseignants] = useState([])

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(EMPTY_FORM)
  const [formErrors, setFormErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const [deleting, setDeleting] = useState(null)
  const [deleteLoading, setDeleteLoading] = useState(false)

  useEffect(() => {
    enseignantsApi.list({ per_page: 1000 }).then((res) => setEnseignants(res.data.data)).catch(() => {})
  }, [])

  const fetchData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await usersApi.list({ search: search || undefined, page })
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
      name: item.name,
      email: item.email,
      role: item.role,
      password: '',
      enseignant_id: item.enseignant_id || '',
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
    const payload = { ...form }
    if (editing && !payload.password) delete payload.password
    if (payload.role !== 'enseignant') payload.enseignant_id = null

    try {
      if (editing) {
        await usersApi.update(editing.id, payload)
        toast.success('Utilisateur mis à jour avec succès.')
      } else {
        await usersApi.create(payload)
        toast.success('Utilisateur créé avec succès.')
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
      await usersApi.remove(deleting.id)
      toast.success('Utilisateur supprimé avec succès.')
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
        title="Utilisateurs"
        subtitle="Gérez les comptes et les rôles des membres du personnel."
        search={search}
        onSearch={setSearch}
        searchPlaceholder="Rechercher un utilisateur…"
        actionLabel="Nouvel utilisateur"
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
              title="Aucun utilisateur"
              description="Créez des comptes pour le personnel."
              action={
                <button type="button" className="btn btn--primary" onClick={openCreate}>
                  + Nouvel utilisateur
                </button>
              }
            />
          ) : (
            <div className="table-wrapper">
              <table className="table">
                <thead>
                  <tr>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Enseignant lié</th>
                    <th className="th-actions">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((item) => (
                    <tr key={item.id}>
                      <td className="cell-strong">{item.name}</td>
                      <td>{item.email}</td>
                      <td>
                        <span className={`badge ${roleBadge(item.role)}`}>{roleLabel(item.role)}</span>
                      </td>
                      <td className="cell-muted">
                        {item.enseignant
                          ? `${item.enseignant.prenom} ${item.enseignant.nom}`
                          : '—'}
                      </td>
                      <td className="cell-actions">
                        <button type="button" className="icon-btn" title="Modifier" onClick={() => openEdit(item)}>
                          ✏️
                        </button>
                        <button type="button" className="icon-btn" title="Supprimer" onClick={() => setDeleting(item)}>
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
        title={editing ? "Modifier l'utilisateur" : 'Nouvel utilisateur'}
        onClose={() => setModalOpen(false)}
        footer={
          <>
            <button type="button" className="btn btn--ghost" onClick={() => setModalOpen(false)} disabled={saving}>
              Annuler
            </button>
            <button type="submit" form="user-form" className="btn btn--primary" disabled={saving}>
              {saving ? 'Enregistrement…' : editing ? 'Enregistrer' : 'Créer'}
            </button>
          </>
        }
      >
        <form id="user-form" onSubmit={handleSubmit} noValidate>
          <Field label="Nom complet" htmlFor="name" required error={formErrors.name?.[0]}>
            <Input id="name" name="name" value={form.name} onChange={handleChange} placeholder="Ex : Amadou Sy" error={formErrors.name?.[0]} />
          </Field>
          <Field label="Adresse e-mail" htmlFor="email" required error={formErrors.email?.[0]}>
            <Input id="email" type="email" name="email" value={form.email} onChange={handleChange} placeholder="user@ecole.com" error={formErrors.email?.[0]} />
          </Field>
          <div className="form-grid">
            <Field label="Rôle" htmlFor="role" required error={formErrors.role?.[0]}>
              <Select id="role" name="role" value={form.role} onChange={handleChange}>
                {ROLES.map((r) => (
                  <option key={r.value} value={r.value}>
                    {r.label}
                  </option>
                ))}
              </Select>
            </Field>
            <Field
              label="Mot de passe"
              htmlFor="password"
              required={!editing}
              error={formErrors.password?.[0]}
              hint={editing ? 'Laisser vide pour ne pas changer' : '6 caractères minimum'}
            >
              <Input
                id="password"
                type="password"
                name="password"
                value={form.password}
                onChange={handleChange}
                autoComplete="new-password"
                error={formErrors.password?.[0]}
              />
            </Field>
          </div>
          {form.role === 'enseignant' && (
            <Field label="Associer à un enseignant" htmlFor="enseignant_id" error={formErrors.enseignant_id?.[0]}>
              <Select id="enseignant_id" name="enseignant_id" value={form.enseignant_id} onChange={handleChange}>
                <option value="">— Aucun —</option>
                {enseignants.map((e) => (
                  <option key={e.id} value={e.id}>
                    {e.prenom} {e.nom}
                  </option>
                ))}
              </Select>
            </Field>
          )}
        </form>
      </Modal>

      <ConfirmDialog
        open={Boolean(deleting)}
        title="Supprimer l'utilisateur"
        message={deleting ? `Voulez-vous vraiment supprimer le compte de « ${deleting.name} » ?` : ''}
        loading={deleteLoading}
        onConfirm={confirmDelete}
        onCancel={() => setDeleting(null)}
      />
    </div>
  )
}
