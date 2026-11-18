import { useEffect, useState } from 'react'
import Modal from './Modal'
import { Field, Input } from './Field'
import { useToast } from './Toast'
import { paiementEnLigneApi } from '../api/endpoints'
import { formatMontant, monnaie } from '../utils/pays'

/**
 * Paiement en ligne d'un frais par le parent.
 * - CinetPay : redirection vers la page sécurisée du prestataire (mobile money, carte), retour sur l'espace parent.
 * - Simulation (hors production) : l'issue est choisie à l'écran, pour essayer le parcours.
 */
export default function PaiementFrais({ frais, onClose, onPaye }) {
  const toast = useToast()
  const [montant, setMontant] = useState('')
  const [erreur, setErreur] = useState(null)
  const [envoi, setEnvoi] = useState(false)
  const [simulation, setSimulation] = useState(null)

  useEffect(() => {
    setMontant(frais ? String(Math.floor(Number(frais.reste))) : '')
    setErreur(null)
    setSimulation(null)
  }, [frais])

  if (!frais) return null

  const payer = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    setErreur(null)
    try {
      const res = await paiementEnLigneApi.payer(frais.id, Number(montant))
      if (res.data.url_paiement) {
        window.location.assign(res.data.url_paiement)
        return
      }
      setSimulation(res.data)
    } catch (err) {
      setErreur(err.errors?.montant?.[0] || err.message)
    } finally {
      setEnvoi(false)
    }
  }

  const simuler = async (issue) => {
    setEnvoi(true)
    try {
      const res = await paiementEnLigneApi.simuler(simulation.reference, issue)
      if (res.data.statut === 'reussi') {
        toast.success(`Paiement de ${formatMontant(res.data.montant)} enregistré. Le reçu est disponible auprès de l’école.`)
        onPaye()
      } else {
        toast.error('Paiement non abouti : aucun montant n’a été prélevé.')
      }
      onClose()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modal
      open
      title={simulation ? 'Paiement (simulation)' : 'Payer en ligne'}
      onClose={onClose}
      footer={
        simulation ? (
          <>
            <button key="sim-echec" type="button" className="btn btn--ghost" onClick={() => simuler('echoue')} disabled={envoi}>Simuler un échec</button>
            <button key="sim-ok" type="button" className="btn btn--primary" onClick={() => simuler('reussi')} disabled={envoi}>Simuler un paiement réussi</button>
          </>
        ) : (
          <>
            <button key="annuler" type="button" className="btn btn--ghost" onClick={onClose} disabled={envoi}>Annuler</button>
            <button key="payer" type="submit" form="paiement-frais" className="btn btn--primary" disabled={envoi || !Number(montant)}>
              {envoi ? 'Connexion au paiement…' : `Payer ${formatMontant(montant)}`}
            </button>
          </>
        )
      }
    >
      {simulation ? (
        <p>
          Mode d’essai : aucun prestataire n’est encore configuré par l’école, aucun argent ne circule.
          Référence <strong>{simulation.reference}</strong>, montant <strong>{formatMontant(simulation.montant)}</strong>.
        </p>
      ) : (
        <form id="paiement-frais" onSubmit={payer} noValidate>
          <p className="muted" style={{ marginTop: 0 }}>
            {frais.libelle} ({frais.annee_scolaire}) · reste à payer <strong>{formatMontant(frais.reste)}</strong>.
            Vous pouvez payer en plusieurs fois.
          </p>
          <Field label={`Montant (${monnaie()})`} htmlFor="paiement-montant" required error={erreur}>
            <Input id="paiement-montant" type="number" min="100" step="5" max={Math.floor(Number(frais.reste))} value={montant} onChange={(e) => setMontant(e.target.value)} error={erreur} />
          </Field>
          <p className="field-hint">Vous serez dirigé vers la page sécurisée du prestataire (mobile money ou carte), puis ramené ici.</p>
        </form>
      )}
    </Modal>
  )
}
