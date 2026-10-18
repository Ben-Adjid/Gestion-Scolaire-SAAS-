import { useEffect, useState } from 'react'
import client from '../api/client'
import PageHeader from '../components/PageHeader'
import Modal from '../components/Modal'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Alert from '../components/Alert'
import { useToast } from '../components/Toast'
import { Field, Input, Select } from '../components/Field'

const PLANS = [
  { value: 'starter',    label: '🌱 Starter',    price: 29, color: '#64748b' },
  { value: 'standard',   label: '⭐ Standard',   price: 79, color: '#4f46e5' },
  { value: 'premium',    label: '🏆 Premium',    price: 149, color: '#d97706' },
  { value: 'entreprise', label: '🏢 Entreprise', price: 299, color: '#16a34a' },
]

const STATUTS = [
  { value: 'actif',     label: 'Actif',     bg: '#f0fdf4', color: '#16a34a' },
  { value: 'essai',     label: 'Essai',     bg: '#eff6ff', color: '#2563eb' },
  { value: 'suspendu',  label: 'Suspendu',  bg: '#fef9c3', color: '#a16207' },
  { value: 'expire',    label: 'Expiré',    bg: '#fef2f2', color: '#dc2626' },
]

const planBadge = (plan) => PLANS.find(p => p.value === plan) || PLANS[1]
const statutBadge = (statut) => STATUTS.find(s => s.value === statut) || STATUTS[0]

const EMPTY_FORM = {
  nom: '', responsable: '', telephone: '', adresse: '',
  annee_scolaire: `${new Date().getFullYear()}-${new Date().getFullYear() + 1}`,
  prefixe_matricule: '', plan: 'standard', abonnement_fin: '',
  admin_name: '', admin_email: '', admin_password: '',
}

export default function SuperAdmin() {
  const toast = useToast()
  const [stats, setStats] = useState(null)
  const [etablissements, setEtablissements] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('')

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(EMPTY_FORM)
  const [formErrors, setFormErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const fetchAll = async () => {
    setLoading(true)
    setError(null)
    try {
      const [dashRes, listRes] = await Promise.all([
        client.get('/super-admin/dashboard'),
        client.get('/super-admin/etablissements'),
      ])
      setStats(dashRes.data.stats)
      setEtablissements(listRes.data.data)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { fetchAll() }, [])

  const visibleEtablissements = etablissements.filter((etab) => {
    const matchesSearch = !search.trim()
      || `${etab.nom} ${etab.responsable || ''}`.toLowerCase().includes(search.trim().toLowerCase())
    return matchesSearch && (!statusFilter || etab.statut_abonnement === statusFilter)
  })

  const planTotals = PLANS.map((plan) => ({
    ...plan,
    total: etablissements.filter((etab) => etab.plan === plan.value).length,
  }))

  const openCreate = () => {
    setEditing(null)
    setForm(EMPTY_FORM)
    setFormErrors({})
    setModalOpen(true)
  }

  const openEdit = (e) => {
    setEditing(e)
    setForm({
      nom: e.nom || '', responsable: e.responsable || '',
      telephone: e.telephone || '', adresse: e.adresse || '',
      annee_scolaire: e.annee_scolaire || '',
      prefixe_matricule: '', plan: e.plan || 'standard',
      statut_abonnement: e.statut_abonnement || 'actif',
      abonnement_fin: e.abonnement_fin || '',
      admin_name: '', admin_email: '', admin_password: '',
    })
    setFormErrors({})
    setModalOpen(true)
  }

  const handleChange = (e) => {
    const { name, value } = e.target
    setForm(prev => ({ ...prev, [name]: value }))
    setFormErrors(prev => ({ ...prev, [name]: undefined }))
  }

  const handleSubmit = async (ev) => {
    ev.preventDefault()
    setSaving(true)
    setFormErrors({})
    try {
      if (editing) {
        await client.put(`/super-admin/etablissements/${editing.id}`, form)
        toast.success(`"${form.nom}" mis à jour.`)
      } else {
        await client.post('/super-admin/etablissements', form)
        toast.success(`Établissement "${form.nom}" créé avec succès !`)
      }
      setModalOpen(false)
      fetchAll()
    } catch (err) {
      if (err.response?.data?.errors) {
        setFormErrors(err.response.data.errors)
      } else {
        toast.error(err.message)
      }
    } finally {
      setSaving(false)
    }
  }

  const handleSuspendre = async (etab) => {
    if (!confirm(`Suspendre "${etab.nom}" ?`)) return
    try {
      await client.post(`/super-admin/etablissements/${etab.id}/suspendre`)
      toast.success(`"${etab.nom}" suspendu.`)
      fetchAll()
    } catch { toast.error('Erreur lors de la suspension.') }
  }

  const handleReactiver = async (etab) => {
    try {
      await client.post(`/super-admin/etablissements/${etab.id}/reactiver`)
      toast.success(`"${etab.nom}" réactivé.`)
      fetchAll()
    } catch { toast.error('Erreur lors de la réactivation.') }
  }

  const handleDelete = async (etab) => {
    if (!confirm(`Supprimer définitivement "${etab.nom}" et TOUTES ses données ? Cette action est irréversible.`)) return
    try {
      await client.delete(`/super-admin/etablissements/${etab.id}`)
      toast.success(`"${etab.nom}" supprimé.`)
      fetchAll()
    } catch { toast.error('Erreur lors de la suppression.') }
  }

  return (
    <div>
      <PageHeader
        title="🛡️ Super Admin — Gestion SaaS"
        subtitle="Gérez tous les établissements abonnés à la plateforme."
        actionLabel="+ Nouvel établissement"
        onAction={openCreate}
      />

      {error && <Alert>{error}</Alert>}

      {/* ── Tableau de bord global ── */}
      {stats && (
        <div className="stat-grid stat-grid--4" style={{ marginBottom: 24 }}>
          <div className="stat-card stat-card--indigo">
            <div className="stat-icon">🏫</div>
            <div className="stat-body">
              <span className="stat-value">{stats.total_etablissements}</span>
              <span className="stat-label">Établissements</span>
            </div>
          </div>
          <div className="stat-card stat-card--green">
            <div className="stat-icon">✅</div>
            <div className="stat-body">
              <span className="stat-value">{stats.actifs}</span>
              <span className="stat-label">Abonnements actifs</span>
            </div>
          </div>
          <div className="stat-card stat-card--amber">
            <div className="stat-icon">⏸️</div>
            <div className="stat-body">
              <span className="stat-value">{stats.suspendus}</span>
              <span className="stat-label">Suspendus</span>
            </div>
          </div>
          <div className="stat-card stat-card--rose">
            <div className="stat-icon">👨‍🎓</div>
            <div className="stat-body">
              <span className="stat-value">{stats.total_eleves}</span>
              <span className="stat-label">Élèves total</span>
            </div>
          </div>
        </div>
      )}

      <div className="card" style={{ marginBottom: 24 }}>
        <div className="card-body">
          <div className="section-heading" style={{ marginBottom: 14 }}>
            <div>
              <h2 style={{ margin: 0 }}>Vue plateforme</h2>
              <p className="text-muted" style={{ margin: '4px 0 0' }}>Répartition des abonnements actifs</p>
            </div>
          </div>
          <div className="stat-grid stat-grid--4">
            {planTotals.map((plan) => (
              <div key={plan.value} className="stat-card" style={{ borderLeft: `4px solid ${plan.color}` }}>
                <div className="stat-body">
                  <span className="stat-label">{plan.label}</span>
                  <span className="stat-value">{plan.total}</span>
                  <span className="cell-muted">{plan.price} € / mois</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* ── Liste des établissements ── */}
      <div className="card">
        <div className="card-body">
          {!loading && etablissements.length > 0 && (
            <div className="toolbar" style={{ marginBottom: 16 }}>
              <Input
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Rechercher un établissement…"
                aria-label="Rechercher un établissement"
              />
              <Select value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)} aria-label="Filtrer par statut">
                <option value="">Tous les statuts</option>
                {STATUTS.map((statut) => <option key={statut.value} value={statut.value}>{statut.label}</option>)}
              </Select>
            </div>
          )}
          {loading ? (
            <div className="page-loader"><Spinner /></div>
          ) : etablissements.length === 0 ? (
            <EmptyState
              title="Aucun établissement"
              description="Cliquez sur « + Nouvel établissement » pour en créer un."
              action={<button className="btn btn--primary" onClick={openCreate}>+ Nouvel établissement</button>}
            />
          ) : visibleEtablissements.length === 0 ? (
            <EmptyState title="Aucun résultat" description="Aucun établissement ne correspond à ces filtres." />
          ) : (
            <div className="table-wrapper">
              <table className="table">
                <thead>
                  <tr>
                    <th>Établissement</th>
                    <th>Plan</th>
                    <th>Statut</th>
                    <th>Fin d'abonnement</th>
                    <th>Élèves</th>
                    <th>Classes</th>
                    <th>Créé le</th>
                    <th className="th-actions">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {visibleEtablissements.map(etab => {
                    const plan   = planBadge(etab.plan)
                    const statut = statutBadge(etab.statut_abonnement)
                    return (
                      <tr key={etab.id}>
                        <td>
                          <div className="cell-strong">{etab.nom}</div>
                          <div className="cell-muted" style={{ fontSize: 12 }}>{etab.responsable || '—'}</div>
                        </td>
                        <td>
                          <span className="badge" style={{ background: plan.color + '22', color: plan.color }}>
                            {plan.label}
                          </span>
                        </td>
                        <td>
                          <span className="badge" style={{ background: statut.bg, color: statut.color }}>
                            {statut.label}
                          </span>
                        </td>
                        <td className="cell-muted">{etab.abonnement_fin || '—'}</td>
                        <td>{etab.etudiants_count}</td>
                        <td>{etab.classes_count}</td>
                        <td className="cell-muted">{etab.created_at}</td>
                        <td>
                          <div className="cell-actions">
                            <button className="icon-btn" title="Modifier" onClick={() => openEdit(etab)}>✏️</button>
                            {etab.statut_abonnement === 'suspendu' ? (
                              <button className="icon-btn" title="Réactiver" onClick={() => handleReactiver(etab)}>▶️</button>
                            ) : (
                              <button className="icon-btn" title="Suspendre" onClick={() => handleSuspendre(etab)}>⏸️</button>
                            )}
                            <button className="icon-btn" title="Supprimer" onClick={() => handleDelete(etab)}>🗑️</button>
                          </div>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      {/* ── Modale création / modification ── */}
      <Modal
        open={modalOpen}
        title={editing ? `Modifier : ${editing.nom}` : 'Nouvel établissement'}
        onClose={() => setModalOpen(false)}
        size="lg"
      >
        <form onSubmit={handleSubmit}>
            <h3 style={{ margin: '0 0 14px' }}>Informations de l'établissement</h3>
            <div className="form-grid">
            <Field label="Nom de l'établissement *" error={formErrors.nom}>
              <Input name="nom" value={form.nom} onChange={handleChange} required />
            </Field>
            <Field label="Responsable" error={formErrors.responsable}>
              <Input name="responsable" value={form.responsable} onChange={handleChange} />
            </Field>
            <Field label="Téléphone" error={formErrors.telephone}>
              <Input name="telephone" value={form.telephone} onChange={handleChange} />
            </Field>
            <Field label="Année scolaire" error={formErrors.annee_scolaire}>
              <Input name="annee_scolaire" value={form.annee_scolaire} onChange={handleChange} />
            </Field>
            <Field label="Plan d'abonnement *" error={formErrors.plan}>
              <Select name="plan" value={form.plan} onChange={handleChange}>
                {PLANS.map(p => <option key={p.value} value={p.value}>{p.label} — {p.price} € / mois</option>)}
              </Select>
            </Field>
            <Field label="Fin d'abonnement" error={formErrors.abonnement_fin}>
              <Input type="date" name="abonnement_fin" value={form.abonnement_fin} onChange={handleChange} />
            </Field>
            {editing && (
              <Field label="Statut de l'abonnement" error={formErrors.statut_abonnement}>
                <Select name="statut_abonnement" value={form.statut_abonnement} onChange={handleChange}>
                  {STATUTS.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
                </Select>
              </Field>
            )}
          </div>

          {!editing && (
            <>
              <hr style={{ margin: '20px 0', borderColor: '#e2e8f0' }} />
              <p style={{ fontWeight: 700, marginBottom: 12, color: '#4f46e5' }}>
                👤 Compte administrateur de l'établissement
              </p>
              <div className="form-grid">
                <Field label="Nom de l'admin *" error={formErrors.admin_name}>
                  <Input name="admin_name" value={form.admin_name} onChange={handleChange} required={!editing} />
                </Field>
                <Field label="Email de l'admin *" error={formErrors.admin_email}>
                  <Input type="email" name="admin_email" value={form.admin_email} onChange={handleChange} required={!editing} />
                </Field>
                <Field label="Mot de passe *" error={formErrors.admin_password}>
                  <Input type="password" name="admin_password" value={form.admin_password} onChange={handleChange} required={!editing} />
                </Field>
              </div>
            </>
          )}

          <div className="modal-footer" style={{ marginTop: 20 }}>
            <button type="button" className="btn btn--ghost" onClick={() => setModalOpen(false)}>Annuler</button>
            <button type="submit" className="btn btn--primary" disabled={saving}>
              {saving ? 'Enregistrement…' : editing ? 'Mettre à jour' : 'Créer l\'établissement'}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
