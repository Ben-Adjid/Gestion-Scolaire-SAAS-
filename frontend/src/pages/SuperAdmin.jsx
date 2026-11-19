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
  nom: '', responsable: '', telephone: '', adresse: '', ville: '', email: '',
  annee_scolaire: `${new Date().getFullYear()}-${new Date().getFullYear() + 1}`,
  prefixe_matricule: '', plan: 'standard', pays: 'TD',
  // Démarrage : essai gratuit ou premier paiement (même circuit qu'un renouvellement, avec facture).
  demarrage: 'essai', jours_essai: 30, duree_mois: 12, montant: '', mode_paiement: 'mobile_money', reference: '',
  admin_name: '', admin_email: '',
}

const MODES_PAIEMENT = [
  { value: 'mobile_money', label: 'Mobile money' },
  { value: 'virement', label: 'Virement' },
  { value: 'especes', label: 'Espèces' },
  { value: 'carte', label: 'Carte bancaire' },
]

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

// Durées proposées pour une prolongation manuelle (paiement reçu hors ligne : espèces, virement direct…).
const DUREES_PROLONGATION = [
  { mois: 1, libelle: '+1 mois (30 jours)' },
  { mois: 3, libelle: '+3 mois' },
  { mois: 12, libelle: '+1 an' },
]

const MODES_HORS_LIGNE = [
  { value: 'especes', label: 'Espèces' },
  { value: 'virement', label: 'Virement bancaire' },
  { value: 'mobile_money', label: 'Mobile money' },
  { value: 'carte', label: 'Carte bancaire' },
]

// Prolongation manuelle : même circuit qu'un renouvellement (période, paiement et facture).
function ProlongerAbonnement({ etab, onClose, onDone }) {
  const toast = useToast()
  const prix = PLANS.find((p) => p.value === etab.plan)?.price || 0
  const [duree, setDuree] = useState(1)
  const [montant, setMontant] = useState(String(prix))
  const [mode, setMode] = useState('especes')
  const [reference, setReference] = useState('')
  const [envoi, setEnvoi] = useState(false)
  const [erreur, setErreur] = useState(null)

  const choisirDuree = (mois) => {
    setDuree(mois)
    setMontant(String(mois * prix))
  }

  const valider = async (ev) => {
    ev.preventDefault()
    setEnvoi(true)
    setErreur(null)
    try {
      const res = await client.post(`/super-admin/etablissements/${etab.id}/renouveler`, {
        plan: etab.plan,
        duree_mois: duree,
        montant: Number(montant),
        mode_paiement: mode,
        reference: reference || null,
      })
      toast.success(res.data.message)
      onDone()
    } catch (err) {
      setErreur(err.message || 'La prolongation n’a pas pu être enregistrée.')
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modal
      open
      title={`Prolonger : ${etab.nom}`}
      onClose={onClose}
      footer={
        <>
          <button type="button" className="btn btn--ghost" onClick={onClose} disabled={envoi}>Annuler</button>
          <button type="submit" form="prolonger-form" className="btn btn--primary" disabled={envoi || Number(montant) < 0}>
            {envoi ? 'Enregistrement…' : 'Enregistrer la prolongation'}
          </button>
        </>
      }
    >
      <form id="prolonger-form" onSubmit={valider}>
        <p className="muted">
          Formule {PLANS.find((p) => p.value === etab.plan)?.label || etab.plan}.
          {etab.abonnement_fin ? ` Échéance actuelle : ${etab.abonnement_fin}.` : ''}
          {' '}La période s’ajoute à l’échéance actuelle si elle n’est pas dépassée.
        </p>
        {erreur && <Alert>{erreur}</Alert>}
        <Field label="Durée" htmlFor="prolonger-duree">
          <Select id="prolonger-duree" value={duree} onChange={(e) => choisirDuree(Number(e.target.value))}>
            {DUREES_PROLONGATION.map((d) => <option key={d.mois} value={d.mois}>{d.libelle}</option>)}
          </Select>
        </Field>
        <Field label="Montant reçu (FCFA)" htmlFor="prolonger-montant" required>
          <Input id="prolonger-montant" type="number" min="0" value={montant} onChange={(e) => setMontant(e.target.value)} />
        </Field>
        <Field label="Mode de paiement" htmlFor="prolonger-mode">
          <Select id="prolonger-mode" value={mode} onChange={(e) => setMode(e.target.value)}>
            {MODES_HORS_LIGNE.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
          </Select>
        </Field>
        <Field label="Référence (facultatif)" htmlFor="prolonger-reference" hint="Numéro de reçu ou de virement, pour la trace comptable.">
          <Input id="prolonger-reference" value={reference} onChange={(e) => setReference(e.target.value)} />
        </Field>
      </form>
    </Modal>
  )
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
  const [paysFilter, setPaysFilter] = useState('')
  const [planFilter, setPlanFilter] = useState('')
  const [prolongerEtab, setProlongerEtab] = useState(null)

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(EMPTY_FORM)
  const [formErrors, setFormErrors] = useState({})
  const [saving, setSaving] = useState(false)
  const [listePays, setListePays] = useState([])
  const [correction, setCorrection] = useState(false)

  useEffect(() => {
    client.get('/pays').then((res) => setListePays(res.data)).catch(() => {})
  }, [])

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
      || `${etab.nom} ${etab.responsable || ''} ${etab.ville || ''} ${etab.email || ''}`.toLowerCase().includes(search.trim().toLowerCase())
    // Page « Écoles à risque » : seulement les écoles actives sous le seuil.
    const aRisque = section !== 'risque'
      || (etab.statut_abonnement === 'actif' && (etab.sante?.score ?? 100) <= SEUIL_RISQUE)
    return matchesSearch && aRisque
      && (!statusFilter || etab.statut_abonnement === statusFilter)
      && (!paysFilter || etab.pays === paysFilter)
      && (!planFilter || etab.plan === planFilter)
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

  const renvoyerActivation = async (etab) => {
    if (!window.confirm(`Renvoyer l’invitation d’activation aux administrateurs de « ${etab.nom} » ?`)) return
    try {
      const res = await client.post(`/super-admin/etablissements/${etab.id}/renvoyer-activation`)
      toast.success(res.data.message)
    } catch (err) {
      toast.error(err.message || 'Envoi impossible.')
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
      telephone: e.telephone || '', adresse: e.adresse || '', ville: e.ville || '', email: e.email || '',
      annee_scolaire: e.annee_scolaire || '',
      prefixe_matricule: e.prefixe_matricule || '', plan: e.plan || 'standard',
      pays: e.pays || 'TD',
      statut_abonnement: e.statut_abonnement || 'actif',
      abonnement_fin: e.abonnement_fin || '',
      motif_correction: '',
    })
    setCorrection(false)
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
        // Sans « correction », formule, statut et échéance ne sont pas envoyés : ils passent par Renouveler / Essai / Suspendre.
        const { plan, statut_abonnement, abonnement_fin, motif_correction, ...infos } = form
        await client.put(`/super-admin/etablissements/${editing.id}`, correction
          ? { ...infos, plan, statut_abonnement, abonnement_fin: abonnement_fin || null, motif_correction }
          : infos)
        toast.success(`"${form.nom}" mis à jour.`)
      } else {
        const { demarrage, jours_essai, duree_mois, montant, mode_paiement, reference, ...base } = form
        await client.post('/super-admin/etablissements', demarrage === 'paiement'
          ? { ...base, demarrage, duree_mois: Number(duree_mois), montant: Number(montant), mode_paiement, reference: reference || null }
          : { ...base, demarrage, jours_essai: Number(jours_essai) })
        toast.success(`Établissement "${form.nom}" créé avec succès !`)
      }
      setModalOpen(false)
      fetchAll()
    } catch (err) {
      const erreurs = err.errors || err.response?.data?.errors
      if (erreurs) {
        setFormErrors(erreurs)
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
              <Select value={paysFilter} onChange={(event) => setPaysFilter(event.target.value)} aria-label="Filtrer par pays">
                <option value="">Tous les pays</option>
                {listePays.map((p) => <option key={p.code} value={p.code}>{p.nom}</option>)}
              </Select>
              <Select value={planFilter} onChange={(event) => setPlanFilter(event.target.value)} aria-label="Filtrer par offre">
                <option value="">Toutes les offres</option>
                {PLANS.map((p) => <option key={p.value} value={p.value}>{p.label}</option>)}
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
                            <button className="btn btn--ghost btn--sm" onClick={() => renvoyerActivation(etab)}>Renvoyer l’activation</button>
                            <button className="btn btn--ghost btn--sm" onClick={() => reinitialiserMotDePasse(etab)}>Mot de passe</button>
                            <button className="btn btn--ghost btn--sm" onClick={() => openEdit(etab)}>Modifier</button>
                            <button className="btn btn--ghost btn--sm" onClick={() => setProlongerEtab(etab)}>Prolonger</button>
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
      {prolongerEtab && (
        <ProlongerAbonnement
          etab={prolongerEtab}
          onClose={() => setProlongerEtab(null)}
          onDone={() => { setProlongerEtab(null); fetchAll() }}
        />
      )}

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
            <Field label="Ville" error={formErrors.ville}>
              <Input name="ville" value={form.ville} onChange={handleChange} />
            </Field>
            <Field label="E-mail de l'établissement" error={formErrors.email}>
              <Input type="email" name="email" value={form.email} onChange={handleChange} />
            </Field>
            <Field label="Année scolaire" error={formErrors.annee_scolaire}>
              <Input name="annee_scolaire" value={form.annee_scolaire} onChange={handleChange} />
            </Field>
            <Field label="Pays" error={formErrors.pays}>
              <Select name="pays" value={form.pays} onChange={handleChange}>
                {listePays.map((p) => <option key={p.code} value={p.code}>{p.nom} ({p.monnaie})</option>)}
              </Select>
            </Field>
          </div>

          {!editing ? (
            <>
              <h3 className="form-titre-section">Formule et démarrage</h3>
              <div className="form-grid">
                <Field label="Formule *" error={formErrors.plan}>
                  <Select name="plan" value={form.plan} onChange={handleChange}>
                    {PLANS.map(p => <option key={p.value} value={p.value}>{p.label} — {p.price.toLocaleString('fr-FR')} FCFA / mois</option>)}
                  </Select>
                </Field>
                <Field label="Démarrage" hint="Un paiement crée la période et la facture, comme « Renouveler ».">
                  <Select name="demarrage" value={form.demarrage} onChange={handleChange}>
                    <option value="essai">Essai gratuit</option>
                    <option value="paiement">Premier paiement reçu</option>
                  </Select>
                </Field>
                {form.demarrage === 'essai' ? (
                  <Field label="Durée de l’essai (jours)" error={formErrors.jours_essai}>
                    <Input type="number" min="1" max="90" name="jours_essai" value={form.jours_essai} onChange={handleChange} />
                  </Field>
                ) : (
                  <>
                    <Field label="Durée payée (mois) *" error={formErrors.duree_mois}>
                      <Input
                        type="number" min="1" max="24" name="duree_mois" value={form.duree_mois}
                        onChange={(e) => setForm((p) => ({ ...p, duree_mois: e.target.value, montant: String((Number(e.target.value) || 0) * (PLANS.find((p) => p.value === form.plan)?.price || 0)) }))}
                      />
                    </Field>
                    <Field label="Montant reçu (FCFA) *" error={formErrors.montant} hint={`Tarif : ${((Number(form.duree_mois) || 0) * (PLANS.find((p) => p.value === form.plan)?.price || 0)).toLocaleString('fr-FR')} FCFA`}>
                      <Input type="number" min="0" name="montant" value={form.montant} onChange={handleChange} />
                    </Field>
                    <Field label="Mode de paiement *" error={formErrors.mode_paiement}>
                      <Select name="mode_paiement" value={form.mode_paiement} onChange={handleChange}>
                        {MODES_PAIEMENT.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
                      </Select>
                    </Field>
                    <Field label="Référence" error={formErrors.reference}>
                      <Input name="reference" value={form.reference} onChange={handleChange} placeholder="N° de transaction" />
                    </Field>
                  </>
                )}
              </div>
            </>
          ) : (
            <>
              <h3 className="form-titre-section">Abonnement</h3>
              <p className="field-hint" style={{ marginBottom: 12 }}>
                Formule {planBadge(editing.plan).label}, {statutBadge(editing.statut_abonnement).label.toLowerCase()}
                {editing.abonnement_fin ? ` jusqu’au ${editing.abonnement_fin.split('-').reverse().join('/')}` : ''}.
                Pour prolonger ou changer de formule, utilisez « Renouveler » (paiement et facture) ou « Essai » dans Abonnements.
              </p>
              <label style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 12 }}>
                <input type="checkbox" checked={correction} onChange={(e) => setCorrection(e.target.checked)} />
                Corriger une erreur de saisie (sans paiement, avec motif au journal)
              </label>
              {correction && (
                <div className="form-grid">
                  <Field label="Formule" error={formErrors.plan}>
                    <Select name="plan" value={form.plan} onChange={handleChange}>
                      {PLANS.map(p => <option key={p.value} value={p.value}>{p.label}</option>)}
                    </Select>
                  </Field>
                  <Field label="Statut" error={formErrors.statut_abonnement}>
                    <Select name="statut_abonnement" value={form.statut_abonnement} onChange={handleChange}>
                      {STATUTS.map(st => <option key={st.value} value={st.value}>{st.label}</option>)}
                    </Select>
                  </Field>
                  <Field label="Échéance" error={formErrors.abonnement_fin}>
                    <Input type="date" name="abonnement_fin" value={form.abonnement_fin} onChange={handleChange} />
                  </Field>
                  <Field label="Motif de la correction *" error={formErrors.motif_correction}>
                    <Input name="motif_correction" value={form.motif_correction} onChange={handleChange} placeholder="Ex : date saisie par erreur" />
                  </Field>
                </div>
              )}
            </>
          )}

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
              </div>
              <p className="field-hint" style={{ marginTop: 8 }}>
                L’administrateur reçoit un e-mail d’activation : il choisit lui-même son mot de passe. Le lien est valable 72 heures ; vous pouvez le renvoyer depuis la liste des écoles.
              </p>
            </>
          )}

        </form>
      </Modal>
    </div>
  )
}
