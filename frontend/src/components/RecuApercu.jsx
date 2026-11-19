import { useState } from 'react'
import Modal from './Modal'
import Spinner from './Spinner'
import { useToast } from './Toast'
import { construireRecu, downloadRecuPdf } from '../utils/pdf'

// Aperçu du reçu à l'écran : mêmes données que le PDF, avec téléchargement et impression directe.
export default function RecuApercu({ recu, onClose }) {
  const toast = useToast()
  const [enCours, setEnCours] = useState(false)

  if (!recu) return null
  const { paiement, frais, eleve, etablissement } = recu
  const r = construireRecu({ paiement, frais, eleve, etablissement })
  const logo = etablissement.logo_path ? `/${etablissement.logo_path}` : '/icons/icon-192x192.png'
  const lieu = [r.ecole.adresse, r.ecole.ville, r.ecole.pays].filter(Boolean).join(' — ')
  const contact = [r.ecole.telephone ? `Tél : ${r.ecole.telephone}` : '', r.ecole.email].filter(Boolean).join(' · ')

  const telecharger = async () => {
    setEnCours(true)
    try {
      await downloadRecuPdf({ paiement, frais, eleve, etablissement, filename: `${r.numero}.pdf` })
    } catch {
      toast.error('Le reçu n’a pas pu être généré. Réessayez.')
    } finally {
      setEnCours(false)
    }
  }

  return (
    <Modal
      open
      title="Reçu de paiement"
      onClose={onClose}
      size="lg"
      footer={
        <>
          <button type="button" className="btn btn--ghost" onClick={onClose}>Fermer</button>
          <button type="button" className="btn btn--ghost" onClick={() => window.print()}>Imprimer</button>
          <button type="button" className="btn btn--primary" onClick={telecharger} disabled={enCours}>
            {enCours ? <><Spinner size="sm" /> Préparation…</> : 'Télécharger le PDF'}
          </button>
        </>
      }
    >
      <section className="recu-imprimable" aria-label="Reçu de paiement">
        <header className="recu-entete">
          <div className="recu-marque">
            <img src={logo} alt="" className="recu-logo" />
            <div>
              <h2>{r.ecole.nom}</h2>
              {lieu && <p>{lieu}</p>}
              {contact && <p>{contact}</p>}
            </div>
          </div>
          <div className="recu-annee">
            <span>Année scolaire</span>
            <strong>{r.ecole.anneeScolaire || '—'}</strong>
          </div>
        </header>

        <h3 className="recu-titre">Reçu de paiement / Quittance de caisse</h3>

        <div className="recu-references">
          <strong>N° de reçu : {r.numero}</strong>
          <span>Date de l’opération : {r.dateHeure}</span>
        </div>

        <div className="recu-eleve">
          <div><span>Élève</span><strong>{r.eleve.nom}</strong></div>
          <div><span>Matricule</span><strong>{r.eleve.matricule}</strong></div>
          <div><span>Classe et niveau</span><strong>{r.eleve.classe}</strong></div>
          <div><span>Payeur</span><strong>{r.payeur}</strong></div>
        </div>

        <div className="recu-tableau-wrap">
          <table className="recu-tableau">
            <thead>
              <tr>
                <th>Libellé des frais</th>
                <th className="recu-droite">Montant exigible</th>
                <th className="recu-droite">Encaissé ce jour</th>
                <th className="recu-droite">Mode de règlement</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>{r.frais.libelle}</td>
                <td className="recu-droite">{r.frais.exigible}</td>
                <td className="recu-droite"><strong>{r.montantEncaisse}</strong></td>
                <td className="recu-droite">{r.mode}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <dl className="recu-situation">
          <div><dt>Total déjà encaissé</dt><dd>{r.frais.totalPaye}</dd></div>
          <div><dt>Reste à payer</dt><dd>{r.frais.reste}</dd></div>
        </dl>

        <div className="recu-montant">
          <span>Montant encaissé</span>
          <strong>{r.montantEncaisse}</strong>
        </div>

        <span className={`recu-badge ${r.frais.soldé ? 'recu-badge--solde' : 'recu-badge--reste'}`}>
          {r.frais.soldé ? 'SOLDÉ' : `RESTE À PAYER : ${r.frais.reste}`}
        </span>

        <div className="recu-bas">
          <div>
            <p>Encaissé par : {r.agent || '—'}</p>
            {r.reference && <p>Référence : {r.reference}</p>}
          </div>
          <div className="recu-cachet">
            <span>Pour acquit</span>
            <em>Cachet &amp; signature de l’établissement</em>
          </div>
        </div>

        <footer className="recu-pied">
          <p>Ce reçu est délivré à titre de justificatif de paiement officiel. Aucun duplicata ne sera fourni sans motif valable.</p>
          <p>Édité le {new Date().toLocaleDateString('fr-FR')} — {r.ecole.nom}</p>
        </footer>
      </section>
    </Modal>
  )
}
