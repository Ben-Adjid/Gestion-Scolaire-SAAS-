import { useCallback, useEffect, useState } from 'react'
import { autorisationsSortieApi, ouvrirDocument } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Alert from '../components/Alert'
import Modal from '../components/Modal'
import { useToast } from '../components/Toast'
import { Field, Input, Select } from '../components/Field'
import { STATUTS_SORTIE } from './SortiesParent'

const aujourdhui = () => new Date().toLocaleDateString('sv-SE')

// Vie scolaire : autorisations de sortie signées par les parents, à valider puis à constater au portail.
export default function AutorisationsSortie() {
  const toast = useToast()
  const [date, setDate] = useState(aujourdhui)
  const [statut, setStatut] = useState('')
  const [liste, setListe] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState(null)
  const [refus, setRefus] = useState(null)
  const [commentaire, setCommentaire] = useState('')
  const [enCours, setEnCours] = useState(null)

  const charger = useCallback(async () => {
    setChargement(true)
    setErreur(null)
    try {
      const res = await autorisationsSortieApi.liste({ date: date || undefined, statut: statut || undefined, per_page: 100 })
      setListe(res.data.data)
    } catch (err) {
      setErreur(err.message)
    } finally {
      setChargement(false)
    }
  }, [date, statut])

  useEffect(() => { charger() }, [charger])

  const agir = async (id, action, message) => {
    setEnCours(id)
    try {
      await action()
      toast.success(message)
      charger()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setEnCours(null)
    }
  }

  const refuser = async (e) => {
    e.preventDefault()
    await agir(refus.id, () => autorisationsSortieApi.decider(refus.id, 'refusee', commentaire), 'Refus enregistré, le parent le verra dans son espace.')
    setRefus(null)
    setCommentaire('')
  }

  const voir = async (a) => {
    try {
      const res = await autorisationsSortieApi.document(a.id)
      ouvrirDocument(res.data)
    } catch (err) {
      toast.error(err.message)
    }
  }

  return (
    <div>
      <PageHeader
        title="Autorisations de sortie"
        subtitle="Sorties anticipées signées par les parents. Validez, puis constatez la sortie au portail : les parents sont prévenus."
        extra={
          <div style={{ display: 'flex', gap: 8 }}>
            <Input type="date" aria-label="Jour" value={date} onChange={(e) => setDate(e.target.value)} />
            <Select aria-label="Statut" value={statut} onChange={(e) => setStatut(e.target.value)}>
              <option value="">Tous les statuts</option>
              {Object.entries(STATUTS_SORTIE).map(([v, { libelle }]) => <option key={v} value={v}>{libelle}</option>)}
            </Select>
          </div>
        }
      />

      {erreur && <Alert>{erreur}</Alert>}

      <div className="card">
        <div className="card-body">
          {chargement ? (
            <div className="page-loader"><Spinner /></div>
          ) : liste.length === 0 ? (
            <EmptyState title="Aucune autorisation" description="Aucune sortie signée pour ce jour." />
          ) : (
            <div className="table-wrapper">
              <table className="table">
                <thead>
                  <tr>
                    <th>Heure</th>
                    <th>Élève</th>
                    <th>Motif / accompagnant</th>
                    <th>Signée par</th>
                    <th>Statut</th>
                    <th className="th-actions">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {liste.map((a) => (
                    <tr key={a.id}>
                      <td className="cell-strong">
                        {String(a.heure_sortie).slice(0, 5)}
                        {!date && <div className="cell-muted">{new Date(a.date_sortie).toLocaleDateString('fr-FR')}</div>}
                      </td>
                      <td>
                        {a.etudiant?.prenom} {a.etudiant?.nom}
                        <div className="cell-muted">{a.etudiant?.classe?.nom}</div>
                      </td>
                      <td>
                        {a.motif}
                        <div className="cell-muted">{a.accompagnant ? `Récupéré par ${a.accompagnant}` : 'Sort seul'}</div>
                      </td>
                      <td>
                        {a.signature_nom}
                        <div className="cell-muted">
                          {a.parent?.telephone || ''} {a.signature_intacte ? '' : '· signature altérée'}
                        </div>
                      </td>
                      <td><span className={`badge ${STATUTS_SORTIE[a.statut]?.badge || ''}`}>{STATUTS_SORTIE[a.statut]?.libelle || a.statut}</span></td>
                      <td className="cell-actions">
                        {a.statut === 'en_attente' && (
                          <>
                            <button type="button" className="btn btn--primary btn--sm" disabled={enCours === a.id}
                              onClick={() => agir(a.id, () => autorisationsSortieApi.decider(a.id, 'validee'), 'Autorisation validée.')}>
                              Valider
                            </button>
                            <button type="button" className="btn btn--ghost btn--sm" onClick={() => setRefus(a)}>Refuser</button>
                          </>
                        )}
                        {a.statut === 'validee' && (
                          <button type="button" className="btn btn--primary btn--sm" disabled={enCours === a.id || !a.signature_intacte}
                            onClick={() => agir(a.id, () => autorisationsSortieApi.sortie(a.id), 'Sortie enregistrée, les parents sont prévenus.')}>
                            Sortie effectuée
                          </button>
                        )}
                        <button type="button" className="btn btn--ghost btn--sm" onClick={() => voir(a)}>Document</button>
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
        open={Boolean(refus)}
        title="Refuser l’autorisation"
        onClose={() => setRefus(null)}
        footer={
          <>
            <button type="button" className="btn btn--ghost" onClick={() => setRefus(null)}>Annuler</button>
            <button type="submit" form="refus-form" className="btn btn--danger" disabled={!commentaire.trim()}>Refuser</button>
          </>
        }
      >
        <form id="refus-form" onSubmit={refuser}>
          <Field label="Raison communiquée au parent" htmlFor="commentaire" required>
            <Input id="commentaire" value={commentaire} onChange={(e) => setCommentaire(e.target.value)} placeholder="Ex : merci d’appeler la vie scolaire" maxLength={255} />
          </Field>
        </form>
      </Modal>
    </div>
  )
}
