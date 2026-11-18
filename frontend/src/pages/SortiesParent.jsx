import { useCallback, useEffect, useState } from 'react'
import { espaceParentApi, ouvrirDocument } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Alert from '../components/Alert'
import Modal from '../components/Modal'
import { useToast } from '../components/Toast'
import { Field, Input, Select } from '../components/Field'
import { useAuth } from '../context/AuthContext'

export const STATUTS_SORTIE = {
  en_attente: { libelle: 'En attente', badge: 'badge--amber' },
  validee: { libelle: 'Validée', badge: 'badge--success' },
  refusee: { libelle: 'Refusée', badge: 'badge--danger' },
  effectuee: { libelle: 'Sortie effectuée', badge: 'badge--success' },
  annulee: { libelle: 'Annulée', badge: '' },
}

const aujourdhui = () => new Date().toLocaleDateString('sv-SE')
const formulaireVide = () => ({ etudiant_id: '', date_sortie: aujourdhui(), heure_sortie: '', motif: '', accompagnant: '', signature_nom: '', attestation: false })

// Autorisations de sortie : le parent remplit, signe (nom tapé + attestation) et suit la décision de l'école.
export default function SortiesParent() {
  const toast = useToast()
  const { user } = useAuth()
  const [enfants, setEnfants] = useState([])
  const [sorties, setSorties] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState(null)
  const [ouvert, setOuvert] = useState(false)
  const [form, setForm] = useState(formulaireVide)
  const [erreurs, setErreurs] = useState({})
  const [envoi, setEnvoi] = useState(false)

  const charger = useCallback(() => {
    espaceParentApi.sorties().then((res) => setSorties(res.data)).catch((e) => setErreur(e.message))
  }, [])

  useEffect(() => {
    Promise.all([espaceParentApi.enfants(), espaceParentApi.sorties()])
      .then(([e, s]) => {
        setEnfants(e.data.enfants)
        setSorties(s.data)
      })
      .catch((e) => setErreur(e.message))
      .finally(() => setChargement(false))
  }, [])

  const ouvrir = () => {
    setForm({ ...formulaireVide(), etudiant_id: enfants[0]?.id ?? '' })
    setErreurs({})
    setOuvert(true)
  }

  const changer = (e) => {
    const { name, value, type, checked } = e.target
    setForm((p) => ({ ...p, [name]: type === 'checkbox' ? checked : value }))
    setErreurs((p) => ({ ...p, [name]: undefined }))
  }

  const signer = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    setErreurs({})
    try {
      const { etudiant_id: id, ...donnees } = form
      await espaceParentApi.signerSortie(id, donnees)
      toast.success('Autorisation signée et transmise à l’établissement.')
      setOuvert(false)
      charger()
    } catch (err) {
      setErreurs(err.errors || {})
      if (!err.errors) toast.error(err.message)
    } finally {
      setEnvoi(false)
    }
  }

  const annuler = async (s) => {
    try {
      await espaceParentApi.annulerSortie(s.id)
      toast.success('Autorisation annulée.')
      charger()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const voir = async (s) => {
    try {
      const res = await espaceParentApi.documentSortie(s.id)
      ouvrirDocument(res.data)
    } catch (err) {
      toast.error(err.message)
    }
  }

  if (chargement) return <Spinner />

  return (
    <div>
      <PageHeader
        title="Autorisations de sortie"
        subtitle="Autorisez votre enfant à quitter l’établissement plus tôt. Votre signature électronique est horodatée."
        actionLabel={enfants.length > 0 ? 'Nouvelle autorisation' : undefined}
        onAction={enfants.length > 0 ? ouvrir : undefined}
      />

      {erreur && <Alert>{erreur}</Alert>}

      <div className="card">
        <div className="card-body">
          {sorties.length === 0 ? (
            <EmptyState title="Aucune autorisation" description="Les autorisations que vous signez apparaîtront ici avec la décision de l’établissement." />
          ) : (
            <div className="table-wrapper">
              <table className="table">
                <thead>
                  <tr>
                    <th>Enfant</th>
                    <th>Date et heure</th>
                    <th>Motif</th>
                    <th>Statut</th>
                    <th className="th-actions">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {sorties.map((s) => (
                    <tr key={s.id}>
                      <td className="cell-strong">{s.etudiant?.prenom} {s.etudiant?.nom}</td>
                      <td>{new Date(s.date_sortie).toLocaleDateString('fr-FR')} à {String(s.heure_sortie).slice(0, 5)}</td>
                      <td>
                        {s.motif}
                        {s.commentaire && <div className="cell-muted">École : {s.commentaire}</div>}
                      </td>
                      <td><span className={`badge ${STATUTS_SORTIE[s.statut]?.badge || ''}`}>{STATUTS_SORTIE[s.statut]?.libelle || s.statut}</span></td>
                      <td className="cell-actions">
                        <button type="button" className="btn btn--ghost btn--sm" onClick={() => voir(s)}>Document</button>
                        {['en_attente', 'validee'].includes(s.statut) && (
                          <button type="button" className="btn btn--ghost btn--sm" onClick={() => annuler(s)}>Annuler</button>
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
        title="Autoriser une sortie"
        onClose={() => setOuvert(false)}
        footer={
          <>
            <button type="button" className="btn btn--ghost" onClick={() => setOuvert(false)} disabled={envoi}>Annuler</button>
            <button type="submit" form="sortie-form" className="btn btn--primary" disabled={envoi || !form.attestation}>
              {envoi ? 'Signature…' : 'Signer et envoyer'}
            </button>
          </>
        }
      >
        <form id="sortie-form" onSubmit={signer} noValidate>
          <h3 className="form-titre-section">Sortie</h3>
          <Field label="Enfant" htmlFor="etudiant_id" required>
            <Select id="etudiant_id" name="etudiant_id" value={form.etudiant_id} onChange={changer}>
              {enfants.map((e) => <option key={e.id} value={e.id}>{e.prenom} {e.nom}</option>)}
            </Select>
          </Field>
          <div className="form-grid">
            <Field label="Date" htmlFor="date_sortie" required error={erreurs.date_sortie?.[0]}>
              <Input id="date_sortie" type="date" name="date_sortie" min={aujourdhui()} value={form.date_sortie} onChange={changer} error={erreurs.date_sortie?.[0]} />
            </Field>
            <Field label="Heure de sortie" htmlFor="heure_sortie" required error={erreurs.heure_sortie?.[0]}>
              <Input id="heure_sortie" type="time" name="heure_sortie" value={form.heure_sortie} onChange={changer} error={erreurs.heure_sortie?.[0]} />
            </Field>
          </div>
          <Field label="Motif" htmlFor="motif" required error={erreurs.motif?.[0]}>
            <Input id="motif" name="motif" value={form.motif} onChange={changer} placeholder="Ex : rendez-vous médical" error={erreurs.motif?.[0]} />
          </Field>
          <Field label="Personne qui vient chercher l’enfant" htmlFor="accompagnant" hint="Laissez vide si l’enfant sort seul." error={erreurs.accompagnant?.[0]}>
            <Input id="accompagnant" name="accompagnant" value={form.accompagnant} onChange={changer} placeholder="Nom et lien de parenté" />
          </Field>

          <h3 className="form-titre-section">Signature</h3>
          <Field label="Votre nom complet" htmlFor="signature_nom" required hint={`Tel qu’il figure sur votre compte : ${user?.name ?? ''}`} error={erreurs.signature_nom?.[0]}>
            <Input id="signature_nom" name="signature_nom" value={form.signature_nom} onChange={changer} autoComplete="name" error={erreurs.signature_nom?.[0]} />
          </Field>
          {form.signature_nom && <div className="signature-apercu">{form.signature_nom}</div>}
          <label className="case-attestation">
            <input type="checkbox" name="attestation" checked={form.attestation} onChange={changer} />
            <span>Je certifie être le parent ou le responsable légal de cet enfant et l’autoriser à quitter l’établissement aux date et heure indiquées.</span>
          </label>
          {erreurs.attestation?.[0] && <p className="field-error">{erreurs.attestation[0]}</p>}
        </form>
      </Modal>
    </div>
  )
}
