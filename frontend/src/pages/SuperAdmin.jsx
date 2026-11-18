import { useEffect, useState } from 'react'
import client from '../api/client'
import PageHeader from '../components/PageHeader'
import Modal from '../components/Modal'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Alert from '../components/Alert'
import { useToast } from '../components/Toast'
import { Field, Input, Select } from '../components/Field'
import { DemandesAbonnementCard, RenouvellementCard, SupportPlateformeCard } from '../components/SuperAdminCartes'
import GroupesCard from '../components/GroupesCard'
import JournalPlateformeCard from '../components/JournalPlateformeCard'
import FicheEtablissement from '../components/FicheEtablissement'
import ErreursServeurCard from '../components/ErreursServeurCard'

const PLANS = [
  { value: 'standard',   label: 'Standard',   price: 25000,  color: '#1F5C4A' },
  { value: 'vip',        label: 'VIP',        price: 50000,  color: '#C2703D' },
  { value: 'entreprise', label: 'Entreprise', price: 100000, color: '#16a34a' },
]

function AnnonceCard() {
  const toast = useToast()
  const [titre, setTitre] = useState('')
  const [contenu, setContenu] = useState('')
  const [cible, setCible] = useState('tous')
  const [plan, setPlan] = useState('standard')
  const [envoi, setEnvoi] = useState(false)

  const envoyer = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    try {
      const res = await client.post('/super-admin/annonces', { titre, contenu, cible, plan: cible === 'plan' ? plan : undefined })
      toast.success(res.data.message)
      setTitre('')
      setContenu('')
    } catch (err) {
      toast.error(err.message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <form className="card" style={{ marginBottom: 16 }} onSubmit={envoyer}>
      <div className="card-header"><h2>Annonce à la communauté</h2></div>
      <div className="card-body">
        <p className="muted" style={{ marginBottom: 12 }}>
          Un message de la plateforme, envoyé aux administrateurs des établissements choisis.
        </p>
        <Field label="Titre" htmlFor="annonce-titre" required>
          <Input id="annonce-titre" value={titre} onChange={(e) => setTitre(e.target.value)} />
        </Field>
        <Field label="Message" htmlFor="annonce-contenu" required>
          <textarea id="annonce-contenu" className="input" rows={4} value={contenu} onChange={(e) => setContenu(e.target.value)} />
        </Field>
        <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', alignItems: 'end' }}>
          <Field label="Destinataires" htmlFor="annonce-cible">
            <Select id="annonce-cible" value={cible} onChange={(e) => setCible(e.target.value)}>
              <option value="tous">Tous les établissements</option>
              <option value="plan">Une formule seulement</option>
            </Select>
          </Field>
          {cible === 'plan' && (
            <Field label="Formule" htmlFor="annonce-plan">
              <Select id="annonce-plan" value={plan} onChange={(e) => setPlan(e.target.value)}>
                {PLANS.map((p) => <option key={p.value} value={p.value}>{p.label}</option>)}
              </Select>
            </Field>
          )}
          <button type="submit" className="btn btn--primary" disabled={envoi || !titre || !contenu}>
            {envoi ? 'Envoi…' : 'Envoyer l’annonce'}
          </button>
        </div>
      </div>
    </form>
  )
}

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

// Une section par entrée du menu « Plateforme SaaS ».
const TITRES = {
  ecoles: ['Gestion des écoles', 'Établissements abonnés, statistiques et actions sur chaque école.'],
  abonnements: ['Abonnements', 'Renouvellements et historique des paiements des écoles.'],
  groupes: ['Groupes scolaires', 'Plusieurs sites sous un même propriétaire.'],
  annonces: ['Annonces', 'Messages de la plateforme aux administrateurs des écoles.'],
  support: ['Support', 'Demandes envoyées par les écoles à la plateforme.'],
  journal: ['Journal de la plateforme', 'Toutes les actions faites par la plateforme.'],
  risque: ['Écoles à risque', 'Écoles actives dont le score de santé est inférieur ou égal à 50/100.'],
  erreurs: ['Erreurs serveur', 'Surveillance de la production : erreurs inattendues et nombre d’occurrences.'],
}

const SEUIL_RISQUE = 50

// Couleur du score de santé : vert au-dessus de 75, orange jusqu'à 50, rouge en dessous.
function badgeSante(score) {
  if (score === undefined || score === null) return <span className="muted">—</span>
  const classe = score > 75 ? 'badge--success' : score > SEUIL_RISQUE ? 'badge--amber' : 'badge--danger'
  return <span className={`badge ${classe}`}>{score} / 100</span>
}

export default function SuperAdmin({ section = 'ecoles' }) {
  const toast = useToast()
  const [stats, setStats] = useState(null)
  const [etablissements, setEtablissements] = useState([])
  const [loading, setLoading] = useState(true)
  // Incrémenté à chaque rechargement : les cartes qui listent les établissements repartent de données à jour.
  const [version, setVersion] = useState(0)
  const [ficheId, setFicheId] = useState(null)
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
      setVersion((v) => v + 1)
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
    // Page « Écoles à risque » : seulement les écoles actives sous le seuil.
    const aRisque = section !== 'risque'
      || (etab.statut_abonnement === 'actif' && (etab.sante?.score ?? 100) <= SEUIL_RISQUE)
    return matchesSearch && aRisque && (!statusFilter || etab.statut_abonnement === statusFilter)
  })

  // Le mot de passe temporaire s'affiche une seule fois, dans un champ prêt à être copié.
  const reinitialiserMotDePasse = async (etab) => {
    if (!window.confirm(`Réinitialiser le mot de passe de l'administrateur de « ${etab.nom} » ? Ses sessions seront fermées.`)) return
    try {
      const res = await client.post(`/super-admin/etablissements/${etab.id}/reinitialiser-admin`)
      window.prompt(
        `Mot de passe temporaire pour ${res.data.email} (copiez-le maintenant, il ne sera plus affiché) :`,
        res.data.mot_de_passe_temporaire
      )
      toast.success('Mot de passe réinitialisé.')
    } catch (err) {
      toast.error(err.message || 'Réinitialisation impossible.')
    }
  }

  const relancerEcole = async (etab) => {
    if (!window.confirm(`Envoyer une relance par e-mail aux administrateurs de « ${etab.nom} » ?`)) return
    try {
      const res = await client.post(`/super-admin/etablissements/${etab.id}/relancer`)
      toast.success(res.data.message)
    } catch (err) {
      toast.error(err.message || 'Relance impossible.')
    }
  }

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
      prefixe_matricule: e.prefixe_matricule || '', plan: e.plan || 'standard',
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
    // Suppression irréversible : le nom exact de l'école doit être ressaisi.
    const saisie = window.prompt(`Suppression définitive de « ${etab.nom} » et de toutes ses données.\nPour confirmer, saisissez son nom exact :`)
    if (saisie === null) return
    try {
      await client.delete(`/super-admin/etablissements/${etab.id}`, { data: { confirmation: saisie } })
      toast.success(`« ${etab.nom} » supprimé.`)
      fetchAll()
    } catch (err) {
      toast.error(err.message || 'Suppression impossible.')
    }
  }

  // Export CSV téléchargé avec le jeton de session (le lien direct ne porte pas l'en-tête d'authentification).
  const exporterCsv = async () => {
    try {
      const res = await client.get('/super-admin/etablissements/export', { responseType: 'blob' })
      const url = URL.createObjectURL(res.data)
      const lien = document.createElement('a')
      lien.href = url
      lien.download = 'etablissements.xlsx'
      lien.click()
      URL.revokeObjectURL(url)
    } catch (err) {
      toast.error(err.message || 'Export impossible.')
    }
  }

  const titre = TITRES[section] || TITRES.ecoles

  return (
    <div>
      <PageHeader
        title={titre[0]}
        subtitle={titre[1]}
        actionLabel={section === 'ecoles' ? '+ Nouvel établissement' : undefined}
        onAction={section === 'ecoles' ? openCreate : undefined}
      />

      {section === 'annonces' && <AnnonceCard />}
      {section === 'abonnements' && (
        <>
          <DemandesAbonnementCard />
          <RenouvellementCard key={`renouv-${version}`} />
        </>
      )}
      {section === 'groupes' && <GroupesCard key={`groupes-${version}`} />}
      {section === 'journal' && <JournalPlateformeCard />}
      {section === 'erreurs' && <ErreursServeurCard />}
      {section === 'support' && <SupportPlateformeCard />}
      <FicheEtablissement etablissementId={ficheId} onClose={() => setFicheId(null)} onChange={fetchAll} />

      {error && <Alert>{error}</Alert>}

      {(section === 'ecoles' || section === 'risque') && (<>
      {/* ── Tableau de bord global ── */}
      {section === 'ecoles' && stats && (
        <div className="stat-grid stat-grid--4" style={{ marginBottom: 24 }}>
          <div className="stat-card stat-card--indigo">
            <div className="stat-body">
              <span className="stat-value">{stats.total_etablissements}</span>
              <span className="stat-label">Établissements</span>
            </div>
          </div>
          <div className="stat-card stat-card--green">
            <div className="stat-body">
              <span className="stat-value">{stats.actifs}</span>
              <span className="stat-label">Abonnements actifs</span>
            </div>
          </div>
          <div className="stat-card stat-card--amber">
            <div className="stat-body">
              <span className="stat-value">{stats.suspendus}</span>
              <span className="stat-label">Suspendus</span>
            </div>
          </div>
          <div className="stat-card stat-card--rose">
            <div className="stat-body">
              <span className="stat-value">{stats.total_eleves}</span>
              <span className="stat-label">Élèves total</span>
            </div>
          </div>
        </div>
      )}

      {section === 'ecoles' && (
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
                  <span className="cell-muted">{plan.price.toLocaleString('fr-FR')} FCFA / mois</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
      )}

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
              <button type="button" className="btn btn--ghost" onClick={exporterCsv}>Exporter Excel</button>
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
                    <th>Santé</th>
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
                        <td>{badgeSante(etab.sante?.score)}</td>
                        <td className="cell-muted">{etab.abonnement_fin ? etab.abonnement_fin.split('-').reverse().join('/') : '—'}</td>
                        <td>{etab.etudiants_count}</td>
                        <td>{etab.classes_count}</td>
                        <td className="cell-muted">{etab.created_at}</td>
                        <td>
                          <div className="cell-actions">
                            <button className="btn btn--ghost btn--sm" onClick={() => setFicheId(etab.id)}>Fiche</button>
                            <button className="btn btn--ghost btn--sm" onClick={() => relancerEcole(etab)}>Relancer</button>
                            <button className="btn btn--ghost btn--sm" onClick={() => reinitialiserMotDePasse(etab)}>Mot de passe</button>
                            <button className="btn btn--ghost btn--sm" onClick={() => openEdit(etab)}>Modifier</button>
                            {etab.statut_abonnement === 'suspendu' ? (
                              <button className="btn btn--ghost btn--sm" onClick={() => handleReactiver(etab)}>Réactiver</button>
                            ) : (
                              <button className="btn btn--ghost btn--sm" onClick={() => handleSuspendre(etab)}>Suspendre</button>
                            )}
                            <button className="btn btn--ghost btn--sm" onClick={() => handleDelete(etab)}>Supprimer</button>
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

      </>)}

      {/* ── Modale création / modification ── */}
      <Modal
        open={modalOpen}
        title={editing ? `Modifier : ${editing.nom}` : 'Nouvel établissement'}
        onClose={() => setModalOpen(false)}
        size="lg"
        footer={(
          <>
            <button type="button" className="btn btn--ghost" onClick={() => setModalOpen(false)}>Annuler</button>
            <button type="submit" form="form-etablissement" className="btn btn--primary" disabled={saving}>
              {saving ? 'Enregistrement…' : editing ? 'Mettre à jour' : 'Créer l\'établissement'}
            </button>
          </>
        )}
      >
        <form id="form-etablissement" onSubmit={handleSubmit}>
            <h3 className="form-titre-section">Informations de l'établissement</h3>
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
                {PLANS.map(p => <option key={p.value} value={p.value}>{p.label} — {p.price.toLocaleString('fr-FR')} FCFA / mois</option>)}
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
              <h3 className="form-titre-section">Compte administrateur</h3>
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

        </form>
      </Modal>
    </div>
  )
}
