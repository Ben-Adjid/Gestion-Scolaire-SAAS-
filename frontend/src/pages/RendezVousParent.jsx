import { useCallback, useEffect, useState } from 'react'
import { espaceParentApi, rendezVousApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Alert from '../components/Alert'
import Modal from '../components/Modal'
import { useToast } from '../components/Toast'
import { Field, Input, Select, Textarea } from '../components/Field'

export const STATUTS_RDV = {
  demande: { libelle: 'En attente de réponse', badge: 'badge--amber' },
  confirme: { libelle: 'Confirmé', badge: 'badge--success' },
  refuse: { libelle: 'Non accepté', badge: 'badge--danger' },
  annule: { libelle: 'Annulé', badge: '' },
}

export const formatCreneau = (valeur) => {
  const d = new Date(valeur)
  if (Number.isNaN(d.getTime())) return valeur
  return `${d.toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit', month: '2-digit' })} à ${d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`
}

const VIDE = { etudiant_id: '', enseignant_id: '', motif: '', creneaux: ['', '', ''] }

// Rendez-vous parents-professeurs : le parent propose jusqu'à trois créneaux, l'enseignant en confirme un.
export default function RendezVousParent() {
  const toast = useToast()
  const [enfants, setEnfants] = useState([])
  const [liste, setListe] = useState([])
  const [enseignants, setEnseignants] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState(null)
  const [ouvert, setOuvert] = useState(false)
  const [form, setForm] = useState(VIDE)
  const [erreurs, setErreurs] = useState({})
  const [envoi, setEnvoi] = useState(false)

  const charger = useCallback(() => {
    rendezVousApi.mesRendezVous().then((r) => setListe(r.data)).catch((e) => setErreur(e.message))
  }, [])

  useEffect(() => {
    Promise.all([espaceParentApi.enfants(), rendezVousApi.mesRendezVous()])
      .then(([e, r]) => {
        setEnfants(e.data.enfants)
        setListe(r.data)
      })
      .catch((e) => setErreur(e.message))
      .finally(() => setChargement(false))
  }, [])

  // Les enseignants proposés sont ceux de la classe de l'enfant choisi.
  useEffect(() => {
    if (!form.etudiant_id) return
    setEnseignants([])
    rendezVousApi.enseignants(form.etudiant_id).then((r) => setEnseignants(r.data)).catch(() => {})
  }, [form.etudiant_id])

  const ouvrir = () => {
    setForm({ ...VIDE, etudiant_id: enfants[0]?.id ?? '' })
    setErreurs({})
    setOuvert(true)
  }

  const changer = (e) => setForm((p) => ({ ...p, [e.target.name]: e.target.value, ...(e.target.name === 'etudiant_id' ? { enseignant_id: '' } : {}) }))
  const changerCreneau = (i, v) => setForm((p) => ({ ...p, creneaux: p.creneaux.map((c, j) => (j === i ? v : c)) }))

  const envoyer = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    setErreurs({})
    try {
      await rendezVousApi.demander(form.etudiant_id, {
        enseignant_id: Number(form.enseignant_id),
        motif: form.motif,
        creneaux: form.creneaux.filter(Boolean),
      })
      toast.success('Demande envoyée à l’enseignant.')
      setOuvert(false)
      charger()
    } catch (err) {
      setErreurs(err.errors || {})
      if (!err.errors) toast.error(err.message)
    } finally {
      setEnvoi(false)
    }
  }

  const annuler = async (r) => {
    try {
      await rendezVousApi.annuler(r.id)
      toast.success('Rendez-vous annulé.')
      charger()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const erreurCreneaux = erreurs.creneaux?.[0] || Object.entries(erreurs).find(([k]) => k.startsWith('creneaux.'))?.[1]?.[0]
  const maintenant = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16)

  if (chargement) return <Spinner />

  return (
    <div>
      <PageHeader
        title="Rendez-vous avec les enseignants"
        subtitle="Proposez jusqu’à trois créneaux ; l’enseignant confirme celui qui lui convient."
        actionLabel={enfants.length ? 'Demander un rendez-vous' : undefined}
        onAction={enfants.length ? ouvrir : undefined}
      />
      {erreur && <Alert>{erreur}</Alert>}

      <div className="card">
        <div className="card-body">
          {liste.length === 0 ? (
            <EmptyState title="Aucun rendez-vous" description="Vos demandes et les réponses des enseignants apparaîtront ici." />
          ) : (
            <div className="table-wrapper">
              <table className="table">
                <thead>
                  <tr><th>Enseignant</th><th>Enfant</th><th>Créneau</th><th>Statut</th><th className="th-actions">Actions</th></tr>
                </thead>
                <tbody>
                  {liste.map((r) => (
                    <tr key={r.id}>
                      <td className="cell-strong">{r.enseignant?.prenom} {r.enseignant?.nom}<div className="cell-muted">{r.motif}</div></td>
                      <td>{r.etudiant?.prenom} {r.etudiant?.nom}</td>
                      <td>
                        {r.date_retenue
                          ? <strong>{formatCreneau(r.date_retenue)}{r.lieu ? ` · ${r.lieu}` : ''}</strong>
                          : (r.creneaux_proposes || []).map((c) => <div key={c} className="cell-muted">{formatCreneau(c)}</div>)}
                        {r.reponse && <div className="cell-muted">Réponse : {r.reponse}</div>}
                      </td>
                      <td><span className={`badge ${STATUTS_RDV[r.statut]?.badge || ''}`}>{STATUTS_RDV[r.statut]?.libelle || r.statut}</span></td>
                      <td className="cell-actions">
                        {['demande', 'confirme'].includes(r.statut) && (
                          <button type="button" className="btn btn--ghost btn--sm" onClick={() => annuler(r)}>Annuler</button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      <Modal
        open={ouvert}
        title="Demander un rendez-vous"
        onClose={() => setOuvert(false)}
        footer={
          <>
            <button type="button" className="btn btn--ghost" onClick={() => setOuvert(false)} disabled={envoi}>Annuler</button>
            <button type="submit" form="rdv-form" className="btn btn--primary" disabled={envoi || !form.enseignant_id || !form.creneaux.some(Boolean)}>
              {envoi ? 'Envoi…' : 'Envoyer la demande'}
            </button>
          </>
        }
      >
        <form id="rdv-form" onSubmit={envoyer} noValidate>
          <div className="form-grid">
            <Field label="Enfant" htmlFor="rdv-enfant" required>
              <Select id="rdv-enfant" name="etudiant_id" value={form.etudiant_id} onChange={changer}>
                {enfants.map((e) => <option key={e.id} value={e.id}>{e.prenom} {e.nom}</option>)}
              </Select>
            </Field>
            <Field label="Enseignant" htmlFor="rdv-prof" required error={erreurs.enseignant_id?.[0]}>
              <Select id="rdv-prof" name="enseignant_id" value={form.enseignant_id} onChange={changer}>
                <option value="">{enseignants.length ? '— Choisir —' : 'Aucun enseignant affecté à la classe'}</option>
                {enseignants.map((p) => (
                  <option key={p.id} value={p.id}>{p.nom}{p.matieres.length ? ` — ${p.matieres.join(', ')}` : ''}</option>
                ))}
              </Select>
            </Field>
          </div>
          <Field label="Objet du rendez-vous" htmlFor="rdv-motif" required error={erreurs.motif?.[0]}>
            <Textarea id="rdv-motif" name="motif" rows={3} value={form.motif} onChange={changer} placeholder="Ex : comprendre la baisse des notes en mathématiques" />
          </Field>
          <Field label="Vos disponibilités (1 à 3 créneaux)" error={erreurCreneaux}>
            <div className="form-grid">
              {form.creneaux.map((c, i) => (
                <Input key={i} type="datetime-local" min={maintenant} aria-label={`Créneau ${i + 1}`} value={c} onChange={(e) => changerCreneau(i, e.target.value)} />
              ))}
            </div>
          </Field>
        </form>
      </Modal>
    </div>
  )
}
