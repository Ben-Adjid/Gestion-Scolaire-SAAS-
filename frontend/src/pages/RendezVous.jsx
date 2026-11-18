import { useCallback, useEffect, useState } from 'react'
import { rendezVousApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Alert from '../components/Alert'
import Modal from '../components/Modal'
import { useToast } from '../components/Toast'
import { Field, Input, Select } from '../components/Field'
import { STATUTS_RDV, formatCreneau } from './RendezVousParent'

// Côté école : demandes de rendez-vous des parents (l'enseignant voit les siennes, la direction son périmètre).
export default function RendezVous() {
  const toast = useToast()
  const [statut, setStatut] = useState('demande')
  const [liste, setListe] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState(null)
  const [action, setAction] = useState(null) // { type: 'confirmer' | 'refuser', rdv }
  const [form, setForm] = useState({ date_retenue: '', lieu: '', reponse: '' })
  const [erreurs, setErreurs] = useState({})
  const [envoi, setEnvoi] = useState(false)

  const charger = useCallback(async () => {
    setChargement(true)
    setErreur(null)
    try {
      const r = await rendezVousApi.liste({ statut: statut || undefined, per_page: 100 })
      setListe(r.data.data)
    } catch (e) {
      setErreur(e.message)
    } finally {
      setChargement(false)
    }
  }, [statut])

  useEffect(() => { charger() }, [charger])

  const ouvrir = (type, rdv) => {
    setAction({ type, rdv })
    setForm({ date_retenue: rdv.creneaux_proposes?.[0] || '', lieu: '', reponse: '' })
    setErreurs({})
  }

  const valider = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    setErreurs({})
    try {
      if (action.type === 'confirmer') {
        await rendezVousApi.confirmer(action.rdv.id, { date_retenue: form.date_retenue, lieu: form.lieu || null, reponse: form.reponse || null })
        toast.success('Rendez-vous confirmé, le parent est prévenu.')
      } else {
        await rendezVousApi.refuser(action.rdv.id, form.reponse)
        toast.success('Réponse envoyée au parent.')
      }
      setAction(null)
      charger()
    } catch (err) {
      setErreurs(err.errors || {})
      if (!err.errors) toast.error(err.message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <div>
      <PageHeader
        title="Rendez-vous des parents"
        subtitle="Confirmez un des créneaux proposés (ou un autre), ou répondez au parent."
        extra={
          <Select aria-label="Statut" value={statut} onChange={(e) => setStatut(e.target.value)}>
            <option value="">Tous</option>
            {Object.entries(STATUTS_RDV).map(([v, { libelle }]) => <option key={v} value={v}>{libelle}</option>)}
          </Select>
        }
      />
      {erreur && <Alert>{erreur}</Alert>}

      <div className="card">
        <div className="card-body">
          {chargement ? (
            <div className="page-loader"><Spinner /></div>
          ) : liste.length === 0 ? (
            <EmptyState title="Aucun rendez-vous" description="Les demandes des parents apparaîtront ici." />
          ) : (
            <div className="table-wrapper">
              <table className="table">
                <thead>
                  <tr><th>Parent</th><th>Élève</th><th>Enseignant</th><th>Objet</th><th>Créneaux</th><th>Statut</th><th className="th-actions">Actions</th></tr>
                </thead>
                <tbody>
                  {liste.map((r) => (
                    <tr key={r.id}>
                      <td className="cell-strong">{r.parent?.name}<div className="cell-muted">{r.parent?.telephone || r.parent?.email}</div></td>
                      <td>{r.etudiant?.prenom} {r.etudiant?.nom}<div className="cell-muted">{r.etudiant?.classe?.nom}</div></td>
                      <td>{r.enseignant?.prenom} {r.enseignant?.nom}</td>
                      <td>{r.motif}</td>
                      <td>
                        {r.date_retenue
                          ? <strong>{formatCreneau(r.date_retenue)}{r.lieu ? ` · ${r.lieu}` : ''}</strong>
                          : (r.creneaux_proposes || []).map((c) => <div key={c}>{formatCreneau(c)}</div>)}
                      </td>
                      <td><span className={`badge ${STATUTS_RDV[r.statut]?.badge || ''}`}>{STATUTS_RDV[r.statut]?.libelle || r.statut}</span></td>
                      <td className="cell-actions">
                        {r.statut === 'demande' && (
                          <>
                            <button type="button" className="btn btn--primary btn--sm" onClick={() => ouvrir('confirmer', r)}>Confirmer</button>
                            <button type="button" className="btn btn--ghost btn--sm" onClick={() => ouvrir('refuser', r)}>Répondre</button>
                          </>
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
        open={Boolean(action)}
        title={action?.type === 'confirmer' ? 'Confirmer le rendez-vous' : 'Répondre au parent'}
        onClose={() => setAction(null)}
        footer={
          <>
            <button type="button" className="btn btn--ghost" onClick={() => setAction(null)} disabled={envoi}>Annuler</button>
            <button type="submit" form="rdv-action" className={`btn ${action?.type === 'confirmer' ? 'btn--primary' : 'btn--danger'}`} disabled={envoi}>
              {envoi ? 'Envoi…' : action?.type === 'confirmer' ? 'Confirmer' : 'Envoyer la réponse'}
            </button>
          </>
        }
      >
        {action && (
          <form id="rdv-action" onSubmit={valider} noValidate>
            {action.type === 'confirmer' ? (
              <>
                <Field label="Créneau retenu" htmlFor="rdv-date" required error={erreurs.date_retenue?.[0]} hint="Un des créneaux du parent, ou une autre date si aucun ne convient.">
                  <div className="choix-puces">
                    {(action.rdv.creneaux_proposes || []).map((c) => (
                      <button key={c} type="button" className={`choix-puce ${form.date_retenue === c ? 'choix-puce--actif' : ''}`} onClick={() => setForm((p) => ({ ...p, date_retenue: c }))}>
                        {formatCreneau(c)}
                      </button>
                    ))}
                  </div>
                  <Input id="rdv-date" type="datetime-local" value={form.date_retenue} onChange={(e) => setForm((p) => ({ ...p, date_retenue: e.target.value }))} />
                </Field>
                <Field label="Lieu" htmlFor="rdv-lieu" error={erreurs.lieu?.[0]}>
                  <Input id="rdv-lieu" value={form.lieu} onChange={(e) => setForm((p) => ({ ...p, lieu: e.target.value }))} placeholder="Ex : salle des professeurs" />
                </Field>
                <Field label="Message au parent" htmlFor="rdv-msg" hint="Facultatif">
                  <Input id="rdv-msg" value={form.reponse} onChange={(e) => setForm((p) => ({ ...p, reponse: e.target.value }))} />
                </Field>
              </>
            ) : (
              <Field label="Votre réponse" htmlFor="rdv-reponse" required error={erreurs.reponse?.[0]}>
                <Input id="rdv-reponse" value={form.reponse} onChange={(e) => setForm((p) => ({ ...p, reponse: e.target.value }))} placeholder="Ex : je vous propose d’en parler par téléphone" />
              </Field>
            )}
          </form>
        )}
      </Modal>
    </div>
  )
}
