import { useEffect, useState } from 'react'
import client from '../api/client'
import { abonnementsApi } from '../api/endpoints'
import Modal from './Modal'
import Spinner from './Spinner'
import Alert from './Alert'
import { useToast } from './Toast'

const PLAN_LABEL = { standard: 'Standard', vip: 'VIP', entreprise: 'Entreprise' }
const montant = (v) => `${Math.round(Number(v) || 0).toLocaleString('fr-FR')} FCFA`

// Fiche d'une école, pour la plateforme : comptes administrateurs, abonnement, paiements, reçus.
// Aucune donnée d'élève n'est affichée ici.
export default function FicheEtablissement({ etablissementId, onClose, onChange }) {
  const toast = useToast()
  const [fiche, setFiche] = useState(null)
  const [paiements, setPaiements] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [action, setAction] = useState(null)
  const [motDePasse, setMotDePasse] = useState(null)

  const charger = () => {
    setLoading(true)
    setError(null)
    Promise.all([
      client.get(`/super-admin/etablissements/${etablissementId}`),
      abonnementsApi.paiements(etablissementId),
    ])
      .then(([f, p]) => {
        setFiche(f.data)
        setPaiements(p.data)
      })
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    if (etablissementId) charger()
    setMotDePasse(null)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [etablissementId])

  const relancer = async () => {
    setAction('relance')
    try {
      const res = await client.post(`/super-admin/etablissements/${etablissementId}/relancer`)
      toast.success(res.data.message)
      onChange?.()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setAction(null)
    }
  }

  const reinitialiser = async () => {
    setAction('mdp')
    try {
      const res = await client.post(`/super-admin/etablissements/${etablissementId}/reinitialiser-admin`)
      setMotDePasse({ email: res.data.email, valeur: res.data.mot_de_passe_temporaire })
      toast.success('Mot de passe réinitialisé.')
    } catch (err) {
      toast.error(err.message)
    } finally {
      setAction(null)
    }
  }

  // Ouvre un document (reçu ou facture) dans un nouvel onglet, prêt à imprimer ou enregistrer en PDF.
  const ouvrirDocument = async (paiement, type) => {
    try {
      const res = await client.get(`/super-admin/paiements-abonnement/${paiement.id}/${type}`, { responseType: 'blob' })
      const url = URL.createObjectURL(new Blob([res.data], { type: 'text/html' }))
      window.open(url, '_blank', 'noopener')
    } catch (err) {
      toast.error(err.message)
    }
  }

  const ouvrirRecu = (paiement) => ouvrirDocument(paiement, 'recu')

  // Démarre un essai gratuit de 30 jours (l'école garde son accès pendant l'essai).
  const demarrerEssai = async () => {
    if (!window.confirm('Démarrer un essai gratuit de 30 jours pour cette école ?')) return
    try {
      const res = await client.post(`/super-admin/etablissements/${etablissementId}/essai`)
      toast.success(res.data.message)
      setFiche((f) => (f ? { ...f, statut_abonnement: 'essai' } : f))
      onChange?.()
    } catch (err) {
      toast.error(err.message)
    }
  }

  return (
    <Modal
      open={Boolean(etablissementId)}
      title={fiche ? fiche.nom : 'Fiche de l’établissement'}
      onClose={onClose}
      size="lg"
      footer={
        <button type="button" className="btn btn--ghost" onClick={onClose}>Fermer</button>
      }
    >
      {loading && <Spinner />}
      {error && <Alert>{error}</Alert>}

      {fiche && !loading && (
        <>
          <div className="dash-split">
            <div className="dash-split-row"><span>Formule</span><strong>{PLAN_LABEL[fiche.plan] || fiche.plan}</strong></div>
            <div className="dash-split-row"><span>Statut</span><strong>{fiche.statut_abonnement}</strong></div>
            <div className="dash-split-row"><span>Fin d’abonnement</span><strong>{fiche.abonnement_fin ? fiche.abonnement_fin.split('-').reverse().join('/') : '—'}</strong></div>
            <div className="dash-split-row"><span>Groupe scolaire</span><strong>{fiche.groupe || 'Aucun'}</strong></div>
            <div className="dash-split-row"><span>Classes · élèves · comptes</span><strong>{fiche.classes_count} · {fiche.etudiants_count} · {fiche.users_count}</strong></div>
          </div>

          {fiche.sante && (
            <>
              <h3 className="section-subtitle" style={{ marginTop: 18 }}>
                Santé de l’école : {fiche.sante.score} / 100
              </h3>
              <ul className="dash-ranking">
                {fiche.sante.criteres.map((c) => (
                  <li key={c.libelle} className="dash-ranking-row">
                    <div className="dash-ranking-info"><span className="top-name">{c.libelle}</span></div>
                    <span className={`badge ${c.ok ? 'badge--success' : 'badge--amber'}`}>{c.ok ? 'Oui' : 'Non'}</span>
                  </li>
                ))}
              </ul>
            </>
          )}

          <h3 className="section-subtitle" style={{ marginTop: 18 }}>Administrateurs</h3>
          {fiche.administrateurs.length === 0 ? (
            <p className="muted">Aucun administrateur.</p>
          ) : (
            <ul className="dash-ranking">
              {fiche.administrateurs.map((a) => (
                <li key={a.id} className="dash-ranking-row">
                  <div className="dash-ranking-info">
                    <span className="top-name">{a.name}</span>
                    <span className="top-classe">{a.email}</span>
                  </div>
                </li>
              ))}
            </ul>
          )}

          <div className="toolbar" style={{ marginTop: 12, gap: 8 }}>
            <button type="button" className="btn btn--ghost" onClick={relancer} disabled={action !== null}>
              {action === 'relance' ? 'Envoi…' : 'Relancer par e-mail'}
            </button>
            <button type="button" className="btn btn--ghost" onClick={demarrerEssai} disabled={action !== null}>
              Démarrer un essai (30 jours)
            </button>
            <button type="button" className="btn btn--ghost" onClick={reinitialiser} disabled={action !== null}>
              {action === 'mdp' ? 'Réinitialisation…' : 'Réinitialiser le mot de passe admin'}
            </button>
          </div>

          {motDePasse && (
            <div className="info-panel" style={{ marginTop: 12 }}>
              <strong>Mot de passe temporaire pour {motDePasse.email} :</strong>
              <p style={{ fontFamily: 'monospace', fontSize: 16, margin: '6px 0' }}>{motDePasse.valeur}</p>
              <span className="muted">Transmettez-le à l’administrateur. Il ne sera plus affiché.</span>
            </div>
          )}

          <h3 className="section-subtitle" style={{ marginTop: 18 }}>Paiements d’abonnement</h3>
          {paiements.length === 0 ? (
            <p className="muted">Aucun paiement enregistré.</p>
          ) : (
            <table className="table">
              <thead>
                <tr><th>Période</th><th>Formule</th><th>Montant</th><th>Mode</th><th>Reçu</th></tr>
              </thead>
              <tbody>
                {paiements.map((p) => (
                  <tr key={p.id}>
                    <td className="cell-muted">{p.periode_debut?.slice(0, 10)} → {p.periode_fin?.slice(0, 10)}</td>
                    <td>{PLAN_LABEL[p.plan] || p.plan}</td>
                    <td>{montant(p.montant)}</td>
                    <td>{p.mode_paiement}</td>
                    <td>
                      <button type="button" className="btn btn--ghost btn--sm" onClick={() => ouvrirRecu(p)}>Reçu</button>
                      <button type="button" className="btn btn--ghost btn--sm" onClick={() => ouvrirDocument(p, 'facture')}>Facture</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </>
      )}
    </Modal>
  )
}
