import { useCallback, useEffect, useState } from 'react'
import { classesApi, enseignantsApi, matieresApi, usersApi } from '../api/endpoints'
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
import PermissionsGrille from '../components/PermissionsGrille'
import { useAuth } from '../context/AuthContext'
import { useEtablissement } from '../context/EtablissementContext'
import { aLaFonctionnalite } from '../utils/plans'
import { exempleTelephone } from '../utils/pays'
import { lienWhatsApp } from '../utils/whatsapp'

const ROLES = Object.entries(ROLE_LABELS)
  .filter(([value]) => value !== 'super_admin')
  .map(([value, label]) => ({ value, label }))

const EMPTY_FORM = {
  name: '', email: '', telephone: '', role: 'enseignant', password: '', enseignant_id: '', permissions: [],
  matieres: [], specialite: '', affectation: 'Général',
}

// Direction et vie scolaire : on précise où la personne intervient.
const ROLES_AFFECTES = ['proviseur', 'censeur', 'cpe', 'surveillant']
const AFFECTATIONS_DE_BASE = ['Général', 'Collège', 'Lycée']

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
  const { user: moi } = useAuth()
  const { etablissement } = useEtablissement()
  // Permissions personnalisées : VIP et plus. En Standard, les rôles sont fixes.
  const permissionsOuvertes = aLaFonctionnalite(moi, etablissement, 'permissions_granulaires')
  // Un proviseur ne gère pas les comptes de direction (admin, proviseur) : le serveur le refuserait de toute façon.
  const gereCompte = (cible) => moi?.role !== 'proviseur' || !['admin', 'proviseur', 'super_admin'].includes(cible.role)
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [enseignants, setEnseignants] = useState([])
  const [matieres, setMatieres] = useState([])
  const [batiments, setBatiments] = useState([])
  const AFFECTATIONS = [...AFFECTATIONS_DE_BASE, ...batiments]

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(EMPTY_FORM)
  const [formErrors, setFormErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const [deleting, setDeleting] = useState(null)
  const [deleteLoading, setDeleteLoading] = useState(false)

  useEffect(() => {
    enseignantsApi.list({ per_page: 1000 }).then((res) => setEnseignants(res.data.data)).catch(() => {})
    matieresApi.list({ per_page: 1000 }).then((res) => setMatieres(res.data.data ?? res.data)).catch(() => {})
    classesApi.list({ per_page: 1000 })
      .then((res) => setBatiments([...new Set((res.data.data || []).map((c) => c.batiment).filter(Boolean))].sort()))
      .catch(() => {})
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
      telephone: item.telephone || '',
      permissions: (item.permissions || []).map((p) => p.code),
      matieres: (item.enseignant?.matieres || []).map((m) => m.id),
      specialite: item.enseignant?.specialite || '',
      affectation: item.affectation || 'Général',
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
    if (payload.role !== 'enseignant') {
      payload.enseignant_id = null
      delete payload.matieres
      delete payload.specialite
    } else {
      payload.enseignant_id = payload.enseignant_id || null
    }
    if (!ROLES_AFFECTES.includes(payload.role)) payload.affectation = null

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

  const basculerMatiere = (id) =>
    setForm((prev) => ({
      ...prev,
      matieres: prev.matieres.includes(id) ? prev.matieres.filter((m) => m !== id) : [...prev.matieres, id],
    }))

  // Fiche enseignant existante choisie : on reprend ses matières pour les afficher cochées.
  const choisirFiche = (e) => {
    const id = e.target.value
    const fiche = enseignants.find((x) => String(x.id) === id)
    setForm((prev) => ({
      ...prev,
      enseignant_id: id,
      matieres: fiche?.matieres ? fiche.matieres.map((m) => m.id) : prev.matieres,
      specialite: fiche?.specialite ?? prev.specialite,
    }))
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
                    <th>Affectation / fiche</th>
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
                          ? [`${item.enseignant.prenom} ${item.enseignant.nom}`, (item.enseignant.matieres || []).map((m) => m.nom).join(', ')].filter(Boolean).join(' · ')
                          : item.affectation || '—'}
                      </td>
                      <td className="cell-actions">
                        {item.telephone && lienWhatsApp(item.telephone) && (
                          <a className="btn btn--ghost btn--sm" href={lienWhatsApp(item.telephone, `Bonjour ${item.name}, `)} target="_blank" rel="noopener noreferrer">WhatsApp</a>
                        )}
                        {gereCompte(item) ? (
                          <>
                            <button type="button" className="btn btn--ghost btn--sm" onClick={() => openEdit(item)}>Modifier</button>
                            <button type="button" className="btn btn--ghost btn--sm" onClick={() => setDeleting(item)}>Supprimer</button>
                          </>
                        ) : (
                          <span className="muted">Direction</span>
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
          <h3 className="form-titre-section">Identité du compte</h3>
          <Field label="Nom complet" htmlFor="name" required error={formErrors.name?.[0]}>
            <Input id="name" name="name" value={form.name} onChange={handleChange} placeholder="Ex : Amadou Sy" error={formErrors.name?.[0]} />
          </Field>
          <Field label="Adresse e-mail" htmlFor="email" required error={formErrors.email?.[0]}>
            <Input id="email" type="email" name="email" value={form.email} onChange={handleChange} placeholder="user@ecole.com" error={formErrors.email?.[0]} />
          </Field>
          <Field
            label="Téléphone (WhatsApp)"
            htmlFor="telephone"
            error={formErrors.telephone?.[0]}
            hint="Pour les comptes parents : reçoivent les absences et convocations sur WhatsApp."
          >
            <Input id="telephone" name="telephone" value={form.telephone} onChange={handleChange} placeholder={exempleTelephone()} error={formErrors.telephone?.[0]} />
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
          {permissionsOuvertes && !['admin', 'super_admin'].includes(form.role) && (
            <PermissionsGrille
              value={form.permissions}
              onChange={(codes) => setForm((prev) => ({ ...prev, permissions: codes }))}
            />
          )}
          {form.role === 'enseignant' && (
            <>
              <h3 className="form-titre-section">Enseignement</h3>
              <Field
                label="Fiche enseignant"
                htmlFor="enseignant_id"
                error={formErrors.enseignant_id?.[0]}
                hint="Par défaut la fiche est créée avec le compte (code de pointage compris)."
              >
                <Select id="enseignant_id" name="enseignant_id" value={form.enseignant_id} onChange={choisirFiche}>
                  <option value="">Créer la fiche avec ce compte</option>
                  {enseignants.map((e) => (
                    <option key={e.id} value={e.id}>
                      Lier à : {e.prenom} {e.nom}
                    </option>
                  ))}
                </Select>
              </Field>
              <Field label="Spécialité" htmlFor="specialite" error={formErrors.specialite?.[0]}>
                <Input id="specialite" name="specialite" value={form.specialite} onChange={handleChange} placeholder="Ex : Mathématiques-Physique" />
              </Field>
              <Field label="Matières enseignées" error={formErrors.matieres?.[0]} hint={matieres.length === 0 ? 'Créez d’abord les matières dans le menu Matières.' : undefined}>
                <div className="choix-puces">
                  {matieres.map((m) => (
                    <label key={m.id} className={`choix-puce ${form.matieres.includes(m.id) ? 'choix-puce--actif' : ''}`}>
                      <input type="checkbox" checked={form.matieres.includes(m.id)} onChange={() => basculerMatiere(m.id)} />
                      {m.nom}
                    </label>
                  ))}
                </div>
              </Field>
            </>
          )}
          {ROLES_AFFECTES.includes(form.role) && (
            <>
              <h3 className="form-titre-section">Affectation</h3>
              <Field
                label={form.role === 'censeur' ? 'Censeur de quel périmètre ?' : 'Périmètre'}
                htmlFor="affectation"
                error={formErrors.affectation?.[0]}
                hint={batiments.length
                  ? '« Général » : tout l’établissement. Sinon, la personne ne voit que les classes du cycle ou du bâtiment choisi.'
                  : '« Général » : tout l’établissement. Pour un périmètre par bâtiment, renseignez d’abord le bâtiment des classes (menu Classes).'}
              >
                <Input id="affectation" name="affectation" value={form.affectation} onChange={handleChange} placeholder="Général" />
              </Field>
              <div className="choix-puces">
                {AFFECTATIONS.map((a) => (
                  <button key={a} type="button" className={`choix-puce ${form.affectation === a ? 'choix-puce--actif' : ''}`} onClick={() => setForm((prev) => ({ ...prev, affectation: a }))}>
                    {a}
                  </button>
                ))}
              </div>
            </>
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
