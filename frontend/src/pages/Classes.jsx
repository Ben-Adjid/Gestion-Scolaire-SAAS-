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
import { Field, Input, Textarea, Select } from '../components/Field'

const EMPTY_FORM = { nom: '', niveau: '', cycle: '', batiment: '', description: '' }

const NIVEAUX = ['6ème', '5ème', '4ème', '3ème', 'Seconde', 'Première', 'Terminale']
const NIVEAUX_COLLEGE = ['6ème', '5ème', '4ème', '3ème']
const cyclePour = (niveau) => (NIVEAUX_COLLEGE.includes(niveau) ? 'college' : 'lycee')
const CYCLES = { college: 'Collège', lycee: 'Lycée' }

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
      cycle: item.cycle || cyclePour(item.niveau),
      batiment: item.batiment || '',
      description: item.description || '',
    })
    setFormErrors({})
    setModalOpen(true)
  }

  const handleChange = (e) => {
    const { name, value } = e.target
    // Le cycle suit le niveau (modifiable ensuite) : il sert au périmètre des censeurs et surveillants.
    setForm((prev) => ({ ...prev, [name]: value, ...(name === 'niveau' ? { cycle: cyclePour(value) } : {}) }))
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
                    <th>Cycle · bâtiment</th>
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
                          <span className="badge badge--exam">Examen</span>
                        )}
                      </td>
                      <td>
                        <span className="badge">{item.niveau}</span>
                      </td>
                      <td>{item.etudiants_count}</td>
                      <td className="cell-muted">{[CYCLES[item.cycle], item.batiment].filter(Boolean).join(' · ') || '—'}</td>
                      <td className="cell-muted">{item.description || '—'}</td>
                      <td className="cell-actions">
                        <button
                          type="button"
                          className="btn btn--ghost btn--sm"
                          onClick={() => openEdit(item)}
                        >Modifier</button>
                        <button
                          type="button"
                          className="btn btn--ghost btn--sm"
                          onClick={() => setDeleting(item)}
                        disabled={item.etudiants_count > 0}
                        title={item.etudiants_count > 0 ? 'Des élèves sont rattachés : déplacez-les avant de supprimer la classe.' : 'Supprimer cette classe'}
                        >Supprimer</button>
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
                list="niveaux-classes"
                error={formErrors.niveau?.[0]}
              />
              <datalist id="niveaux-classes">
                {NIVEAUX.map((n) => <option key={n} value={n} />)}
              </datalist>
            </Field>
          </div>
          <div className="form-grid">
            <Field label="Cycle" htmlFor="cycle" error={formErrors.cycle?.[0]} hint="Déduit du niveau.">
              <Select id="cycle" name="cycle" value={form.cycle || cyclePour(form.niveau)} onChange={handleChange}>
                {Object.entries(CYCLES).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
              </Select>
            </Field>
            <Field label="Bâtiment" htmlFor="batiment" error={formErrors.batiment?.[0]} hint="Optionnel. Sert au périmètre d’un surveillant ou d’un censeur.">
              <Input id="batiment" name="batiment" value={form.batiment} onChange={handleChange} placeholder="Ex : Bâtiment A" />
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
            ? `Voulez-vous vraiment supprimer la classe « ${deleting.nom} » ?`
            : ''
        }
        loading={deleteLoading}
        onConfirm={confirmDelete}
        onCancel={() => setDeleting(null)}
      />
    </div>
  )
}
