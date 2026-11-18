import { useEffect, useState } from 'react'
import { justificationsApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Alert from '../components/Alert'
import { Field, Textarea } from '../components/Field'
import { useToast } from '../components/Toast'

const ONGLETS = [
  { id: 'en_attente', label: 'À traiter' },
  { id: 'acceptee', label: 'Acceptées' },
  { id: 'refusee', label: 'Refusées' },
]

// CPE (et direction) : valide ou refuse les demandes de justification envoyées par les parents.
export default function Justifications() {
  const toast = useToast()
  const [statut, setStatut] = useState('en_attente')
  const [demandes, setDemandes] = useState(null)
  const [commentaires, setCommentaires] = useState({})
  const [erreur, setErreur] = useState(null)
  const [envoiId, setEnvoiId] = useState(null)

  const charger = (filtre) => {
    setDemandes(null)
    justificationsApi.liste(filtre).then((res) => setDemandes(res.data)).catch((e) => setErreur(e.message))
  }

  useEffect(() => {
    charger(statut)
  }, [statut])

  const decider = async (demande, decision) => {
    setEnvoiId(demande.id)
    try {
      await justificationsApi.decider(demande.id, decision, commentaires[demande.id] || null)
      toast.success(decision === 'acceptee' ? 'Absence justifiée.' : 'Demande refusée.')
      charger(statut)
    } catch (err) {
      toast.error(err.message)
    } finally {
      setEnvoiId(null)
    }
  }

  return (
    <div>
      <PageHeader title="Justifications d’absence" subtitle="Les demandes envoyées par les parents, à valider par le CPE." />

      {erreur && <Alert>{erreur}</Alert>}

      <div className="espace-parent-onglets">
        {ONGLETS.map((o) => (
          <button key={o.id} type="button" className={`tab ${statut === o.id ? 'tab--active' : ''}`} onClick={() => setStatut(o.id)}>
            {o.label}
          </button>
        ))}
      </div>

      {!demandes && !erreur && <Spinner />}

      {demandes?.length === 0 && (
        <EmptyState title="Aucune demande" description="Il n’y a pas de demande dans cette catégorie." />
      )}

      {demandes?.map((d) => (
        <article key={d.id} className="card justification-carte">
          <div className="card-body">
            <header className="justification-entete">
              <strong>
                {d.absence?.etudiant ? `${d.absence.etudiant.prenom} ${d.absence.etudiant.nom}` : 'Élève'}
              </strong>
              <span className="muted">Absence du {d.absence?.date} · demandé par {d.demandeur?.name}</span>
            </header>
            <p className="justification-motif">« {d.motif} »</p>

            {statut === 'en_attente' ? (
              <div className="justification-actions">
                <Field label="Commentaire (facultatif)" htmlFor={`com-${d.id}`}>
                  <Textarea
                    id={`com-${d.id}`}
                    rows={2}
                    value={commentaires[d.id] || ''}
                    onChange={(e) => setCommentaires({ ...commentaires, [d.id]: e.target.value })}
                  />
                </Field>
                <div className="form-actions">
                  <button type="button" className="btn btn--ghost" disabled={envoiId === d.id} onClick={() => decider(d, 'refusee')}>
                    Refuser
                  </button>
                  <button type="button" className="btn btn--primary" disabled={envoiId === d.id} onClick={() => decider(d, 'acceptee')}>
                    Accepter et justifier
                  </button>
                </div>
              </div>
            ) : (
              <p className="muted">
                {d.commentaire_decision ? `Commentaire : ${d.commentaire_decision}` : 'Aucun commentaire.'}
              </p>
            )}
          </div>
        </article>
      ))}
    </div>
  )
}
