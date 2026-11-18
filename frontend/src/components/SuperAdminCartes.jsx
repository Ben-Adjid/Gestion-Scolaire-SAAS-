import { useCallback, useEffect, useState } from 'react'
import client from '../api/client'
import { abonnementsApi, supportPlateformeApi, telecharger } from '../api/endpoints'
import { Field, Input, Select, Textarea } from './Field'
import { useToast } from './Toast'
import Modal from './Modal'

const FORMULES = [
  { value: 'standard', label: 'Standard', prix: 25000 },
  { value: 'vip', label: 'VIP', prix: 50000 },
  { value: 'entreprise', label: 'Entreprise', prix: 100000 },
]

const MODES = [
  { value: 'virement', label: 'Virement bancaire' },
  { value: 'especes', label: 'Espèces' },
  { value: 'mobile_money', label: 'Mobile money' },
  { value: 'carte', label: 'Carte bancaire' },
]

const STATUTS = [
  { value: 'ouverte', label: 'Ouverte' },
  { value: 'en_cours', label: 'En cours' },
  { value: 'resolue', label: 'Résolue' },
]

// Renouvellement d'abonnement : enregistre un paiement hors ligne et prolonge la période.
// Le paiement en ligne (carte, mobile money automatisé) reste à brancher sur un prestataire.
export function RenouvellementCard() {
  const toast = useToast()
  const [etablissements, setEtablissements] = useState([])
  const [form, setForm] = useState({ etablissement_id: '', plan: 'standard', duree_mois: 1, montant: 25000, mode_paiement: 'virement', reference: '' })
  const [historique, setHistorique] = useState([])
  const [envoi, setEnvoi] = useState(false)

  useEffect(() => {
    client.get('/super-admin/etablissements').then((res) => {
      setEtablissements(res.data.data)
      if (res.data.data.length) choisirEtablissement(res.data.data[0].id, res.data.data)
    })
  }, [])

  useEffect(() => {
    if (!form.etablissement_id) return
    abonnementsApi.paiements(form.etablissement_id).then((res) => setHistorique(res.data)).catch(() => setHistorique([]))
  }, [form.etablissement_id])

  // La formule proposée est celle que l'école a déjà : on ne reconduit jamais une formule par défaut par erreur.
  const choisirEtablissement = (id, liste = etablissements) => {
    const etab = liste.find((e) => String(e.id) === String(id))
    const plan = etab?.plan && FORMULES.some((f) => f.value === etab.plan) ? etab.plan : 'standard'
    setForm((f) => ({ ...f, etablissement_id: String(id), plan, montant: FORMULES.find((x) => x.value === plan).prix * f.duree_mois }))
  }

  const majPlan = (plan) => {
    const formule = FORMULES.find((f) => f.value === plan)
    setForm({ ...form, plan, montant: formule.prix * form.duree_mois })
  }

  const majDuree = (duree) => {
    const formule = FORMULES.find((f) => f.value === form.plan)
    setForm({ ...form, duree_mois: duree, montant: formule.prix * duree })
  }

  const enregistrer = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    try {
      const res = await abonnementsApi.renouveler(form.etablissement_id, {
        plan: form.plan,
        duree_mois: Number(form.duree_mois),
        montant: Number(form.montant),
        mode_paiement: form.mode_paiement,
        reference: form.reference || null,
      })
      toast.success(res.data.message)
      setForm({ ...form, reference: '' })
      abonnementsApi.paiements(form.etablissement_id).then((r) => setHistorique(r.data))
    } catch (err) {
      toast.error(err.message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <form className="card form-carte" style={{ marginBottom: 16 }} onSubmit={enregistrer}>
      <div className="card-header"><h2>Renouveler un abonnement</h2></div>
      <div className="card-body">
        <section className="form-section">
          <div className="form-grille">
            <Field label="Établissement" htmlFor="renouv-etab" required>
              <Select id="renouv-etab" value={form.etablissement_id} onChange={(e) => choisirEtablissement(e.target.value)}>
                {etablissements.map((e) => <option key={e.id} value={e.id}>{e.nom}</option>)}
              </Select>
            </Field>
            <Field label="Formule" htmlFor="renouv-plan" required>
              <Select id="renouv-plan" value={form.plan} onChange={(e) => majPlan(e.target.value)}>
                {FORMULES.map((f) => <option key={f.value} value={f.value}>{f.label}</option>)}
              </Select>
            </Field>
            <Field label="Durée" htmlFor="renouv-duree" required hint="En mois, de 1 à 24.">
              <Input id="renouv-duree" type="number" min={1} max={24} value={form.duree_mois} onChange={(e) => majDuree(Number(e.target.value))} />
            </Field>
            <Field label="Montant (FCFA)" htmlFor="renouv-montant" required>
              <Input id="renouv-montant" type="number" min={0} value={form.montant} onChange={(e) => setForm({ ...form, montant: e.target.value })} />
            </Field>
            <Field label="Mode de paiement" htmlFor="renouv-mode" required>
              <Select id="renouv-mode" value={form.mode_paiement} onChange={(e) => setForm({ ...form, mode_paiement: e.target.value })}>
                {MODES.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
              </Select>
            </Field>
            <Field label="Référence" htmlFor="renouv-ref" hint="Numéro de virement ou de transaction.">
              <Input id="renouv-ref" value={form.reference} onChange={(e) => setForm({ ...form, reference: e.target.value })} />
            </Field>
          </div>
        </section>

        <div className="form-actions">
          <button type="submit" className="btn btn--primary" disabled={envoi || !form.etablissement_id}>
            {envoi ? 'Enregistrement…' : 'Enregistrer le paiement'}
          </button>
        </div>

        {historique.length > 0 && (
          <div className="table-wrapper" style={{ marginTop: 16 }}>
            <table className="table">
              <thead><tr><th>Période</th><th>Formule</th><th>Montant</th><th>Mode</th><th>Référence</th><th>Reçu</th></tr></thead>
              <tbody>
                {historique.map((p) => (
                  <tr key={p.id}>
                    <td>{p.periode_debut} → {p.periode_fin}</td>
                    <td>{p.plan}</td>
                    <td>{Number(p.montant).toLocaleString('fr-FR')} FCFA</td>
                    <td>{p.mode_paiement}</td>
                    <td>{p.reference || '—'}</td>
                    <td>
                      <button
                        type="button"
                        className="btn btn--ghost btn--sm"
                        onClick={async () => {
                          try {
                            const res = await abonnementsApi.recu(p.id)
                            telecharger(res.data, `recu-abonnement-${p.id}.html`)
                          } catch (err) {
                            toast.error(err.message)
                          }
                        }}
                      >
                        Télécharger
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </form>
  )
}

export function DemandesAbonnementCard() {
  const toast = useToast()
  const [demandes, setDemandes] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState(null)
  const [traitement, setTraitement] = useState(false)
  const [refus, setRefus] = useState(null)
  const [motif, setMotif] = useState('')

  const charger = useCallback(async () => {
    setChargement(true)
    setErreur(null)
    try {
      const res = await abonnementsApi.demandes()
      setDemandes(res.data)
    } catch (err) {
      setErreur(err.message)
    } finally {
      setChargement(false)
    }
  }, [])

  useEffect(() => {
    charger()
  }, [charger])

  const telechargerJustificatif = async (demande) => {
    try {
      const res = await abonnementsApi.justificatifDemande(demande.id)
      telecharger(res.data, `paiement-abonnement-${demande.id}.${demande.justificatif_extension || 'pdf'}`)
    } catch (err) {
      toast.error(err.message)
    }
  }

  const approuver = async (demande) => {
    setTraitement(true)
    try {
      const res = await abonnementsApi.approuverDemande(demande.id)
      toast.success(res.data.message)
      await charger()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setTraitement(false)
    }
  }

  const soumettreRefus = async (e) => {
    e.preventDefault()
    if (!refus) return
    setTraitement(true)
    try {
      const res = await abonnementsApi.refuserDemande(refus.id, motif)
      toast.success(res.data.message)
      setRefus(null)
      setMotif('')
      await charger()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setTraitement(false)
    }
  }

  return (
    <section className="card demandes-abonnement-card" style={{ marginBottom: 16 }}>
      <div className="card-header"><h2>Demandes de renouvellement à vérifier</h2></div>
      <div className="card-body">
        {erreur && <div className="alert alert--error">{erreur}</div>}
        {chargement ? <p className="muted">Chargement…</p> : demandes.length === 0 ? (
          <p className="muted">Aucune demande de paiement.</p>
        ) : (
          <div className="table-wrapper">
            <table className="table">
              <thead><tr><th>Établissement</th><th>Demandeur</th><th>Formule</th><th>Montant</th><th>Paiement</th><th>Référence</th><th>Date</th><th>Justificatif</th><th>Décision</th></tr></thead>
              <tbody>
                {demandes.map((d) => (
                  <tr key={d.id}>
                    <td>{d.etablissement?.nom}</td>
                    <td>{d.demandeur?.name}<br /><span className="muted">{d.demandeur?.email}</span></td>
                    <td>{d.plan} · {d.duree_mois} mois</td>
                    <td>{Number(d.montant).toLocaleString('fr-FR')} FCFA</td>
                    <td>{d.mode_paiement === 'mobile_money' ? 'Mobile Money' : 'Virement'}</td>
                    <td>{d.reference}</td>
                    <td>{new Date(d.created_at).toLocaleDateString('fr-FR')}</td>
                    <td>{d.a_justificatif ? <button type="button" className="btn btn--ghost btn--sm" onClick={() => telechargerJustificatif(d)}>Télécharger</button> : '—'}</td>
                    <td>
                      {d.statut === 'en_attente' ? (
                        <div className="demandes-abonnement-actions">
                          <button type="button" className="btn btn--primary btn--sm" disabled={traitement} onClick={() => approuver(d)}>Valider</button>
                          <button type="button" className="btn btn--ghost btn--sm" disabled={traitement} onClick={() => { setRefus(d); setMotif('') }}>Refuser</button>
                        </div>
                      ) : <span className={`badge ${d.statut === 'approuvee' ? 'badge--success' : 'badge--danger'}`}>{d.statut === 'approuvee' ? 'Validée' : 'Refusée'}</span>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
      <Modal
        open={Boolean(refus)}
        title="Refuser la demande de paiement"
        onClose={() => !traitement && setRefus(null)}
        footer={
          <>
            <button type="button" className="btn btn--ghost" disabled={traitement} onClick={() => setRefus(null)}>Annuler</button>
            <button type="submit" form="refus-abonnement-form" className="btn btn--danger" disabled={traitement || !motif.trim()}>
              {traitement ? 'Enregistrement…' : 'Confirmer le refus'}
            </button>
          </>
        }
      >
        <form id="refus-abonnement-form" onSubmit={soumettreRefus}>
          <Field label="Motif du refus" htmlFor="motif-refus-abonnement" required hint="Ce motif sera visible par l’établissement.">
            <Textarea id="motif-refus-abonnement" value={motif} onChange={(e) => setMotif(e.target.value)} maxLength={1000} rows={4} required />
          </Field>
        </form>
      </Modal>
    </section>
  )
}

// Demandes de support prioritaire de toutes les écoles, avec réponse et suivi du statut.
export function SupportPlateformeCard() {
  const toast = useToast()
  const [demandes, setDemandes] = useState([])
  const [brouillons, setBrouillons] = useState({})

  const charger = () => supportPlateformeApi.liste().then((res) => setDemandes(res.data))

  useEffect(() => {
    charger()
  }, [])

  const repondre = async (demande) => {
    const brouillon = brouillons[demande.id] || {}
    try {
      await supportPlateformeApi.repondre(demande.id, {
        statut: brouillon.statut || demande.statut,
        reponse: brouillon.reponse ?? demande.reponse,
      })
      toast.success('Demande mise à jour.')
      charger()
    } catch (err) {
      toast.error(err.message)
    }
  }

  return (
    <section className="card" style={{ marginBottom: 16 }}>
      <div className="card-header"><h2>Support prioritaire</h2></div>
      <div className="card-body">
        {demandes.length === 0 && <p className="muted">Aucune demande de support.</p>}
        {demandes.map((d) => {
          const brouillon = brouillons[d.id] || {}
          return (
            <article key={d.id} className="support-demande">
              <header>
                <strong>{d.etablissement?.nom} — {d.sujet}</strong>
                <span className="muted">{d.auteur?.name}</span>
              </header>
              <p>{d.message}</p>
              <div className="form-grille">
                <Field label="Statut" htmlFor={`statut-${d.id}`}>
                  <Select
                    id={`statut-${d.id}`}
                    value={brouillon.statut || d.statut}
                    onChange={(e) => setBrouillons({ ...brouillons, [d.id]: { ...brouillon, statut: e.target.value } })}
                  >
                    {STATUTS.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                  </Select>
                </Field>
                <Field label="Réponse" htmlFor={`reponse-${d.id}`}>
                  <Textarea
                    id={`reponse-${d.id}`}
                    rows={2}
                    value={brouillon.reponse ?? d.reponse ?? ''}
                    onChange={(e) => setBrouillons({ ...brouillons, [d.id]: { ...brouillon, reponse: e.target.value } })}
                  />
                </Field>
              </div>
              <div className="form-actions">
                <button type="button" className="btn btn--primary btn--sm" onClick={() => repondre(d)}>Enregistrer</button>
              </div>
            </article>
          )
        })}
      </div>
    </section>
  )
}
