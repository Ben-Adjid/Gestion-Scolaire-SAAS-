import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { renouvellementEtablissementApi } from '../api/endpoints'
import Alert from '../components/Alert'
import { Field, Input, Select } from '../components/Field'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import { useToast } from '../components/Toast'
import { useAuth } from '../context/AuthContext'

const statutLabel = {
  en_attente: 'En attente de vérification',
  approuvee: 'Validée',
  refusee: 'Refusée',
}

export default function Abonnement() {
  const toast = useToast()
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [options, setOptions] = useState(null)
  const [demandes, setDemandes] = useState([])
  const [plan, setPlan] = useState('')
  const [duree, setDuree] = useState('1')
  const [mode, setMode] = useState('')
  const [reference, setReference] = useState('')
  const [justificatif, setJustificatif] = useState(null)
  const [chargement, setChargement] = useState(true)
  const [envoi, setEnvoi] = useState(false)
  const [erreur, setErreur] = useState(null)

  const chargerDemandes = useCallback(async () => {
    const res = await renouvellementEtablissementApi.demandes()
    setDemandes(res.data)
  }, [])

  useEffect(() => {
    Promise.all([renouvellementEtablissementApi.options(), renouvellementEtablissementApi.demandes()])
      .then(([resOptions, resDemandes]) => {
        setOptions(resOptions.data)
        setDemandes(resDemandes.data)
        setPlan(resOptions.data.plan_actuel || resOptions.data.formules[0]?.code || '')
        setMode(resOptions.data.methodes.find((m) => m.disponible)?.code || '')
      })
      .catch((err) => setErreur(err.message))
      .finally(() => setChargement(false))
  }, [])

  const modeSelectionne = options?.methodes.find((m) => m.code === mode)
  const formuleSelectionnee = options?.formules.find((f) => f.code === plan)
  const montant = (formuleSelectionnee?.prix_mensuel || 0) * Number(duree || 0)
  const demandeEnAttente = demandes.some((d) => d.statut === 'en_attente')

  const envoyer = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    setErreur(null)
    try {
      const formulaire = new FormData()
      formulaire.append('plan', plan)
      formulaire.append('duree_mois', duree)
      formulaire.append('mode_paiement', mode)
      formulaire.append('reference', reference.trim())
      if (justificatif) formulaire.append('justificatif', justificatif)
      const res = await renouvellementEtablissementApi.creer(formulaire)
      toast.success(res.data.message)
      setReference('')
      setJustificatif(null)
      await chargerDemandes()
    } catch (err) {
      setErreur(err.message)
    } finally {
      setEnvoi(false)
    }
  }

  if (chargement) return <div className="page-loader"><Spinner /></div>

  if (!options) {
    return <div className="page"><Alert>{erreur || 'Les options de paiement sont indisponibles.'}</Alert></div>
  }

  return (
    <div className="page abonnement-page">
      <PageHeader
        title="Abonnement de l’établissement"
        subtitle="Choisissez une formule et soumettez votre paiement pour vérification."
      />
      {erreur && <Alert type="error">{erreur}</Alert>}
      <section className="card abonnement-paiement">
        <div className="card-header"><h2>Renouveler l’abonnement</h2></div>
        <div className="card-body">
          {demandeEnAttente && (
            <Alert type="success">
              Une demande est déjà en cours de vérification. La plateforme confirmera le renouvellement après contrôle du paiement.
            </Alert>
          )}
          <form onSubmit={envoyer}>
            <div className="form-grid">
              <Field label="Formule" htmlFor="abonnement-plan" required>
                <Select id="abonnement-plan" value={plan} onChange={(e) => setPlan(e.target.value)} required>
                  {options.formules.map((f) => (
                    <option key={f.code} value={f.code}>{f.libelle} — {Number(f.prix_mensuel).toLocaleString('fr-FR')} {options.devise}/mois</option>
                  ))}
                </Select>
              </Field>
              <Field label="Durée" htmlFor="abonnement-duree" required hint="De 1 à 24 mois.">
                <Input id="abonnement-duree" type="number" min="1" max="24" value={duree} onChange={(e) => setDuree(e.target.value)} required />
              </Field>
            </div>
            <div className="abonnement-total">
              <span>Total à régler</span>
              <strong>{montant.toLocaleString('fr-FR')} {options.devise}</strong>
            </div>

            <h3 className="form-titre-section">Mode de paiement manuel</h3>
            {options.methodes.some((m) => m.disponible) ? (
              <>
                <Field label="Mode de paiement" htmlFor="abonnement-mode" required>
                  <Select id="abonnement-mode" value={mode} onChange={(e) => setMode(e.target.value)} required>
                    {options.methodes.filter((m) => m.disponible).map((m) => (
                      <option key={m.code} value={m.code}>{m.libelle}</option>
                    ))}
                  </Select>
                </Field>
                {modeSelectionne && (
                  <div className="abonnement-coordonnees">
                    <strong>Coordonnées pour effectuer le paiement</strong>
                    {modeSelectionne.code === 'mobile_money' ? (
                      <>
                        <p>{modeSelectionne.libelle} : <strong>{modeSelectionne.details.numero}</strong></p>
                        {modeSelectionne.details.titulaire && <p>Titulaire : {modeSelectionne.details.titulaire}</p>}
                      </>
                    ) : (
                      <>
                        {modeSelectionne.details.banque && <p>Banque : {modeSelectionne.details.banque}</p>}
                        {modeSelectionne.details.titulaire && <p>Titulaire : {modeSelectionne.details.titulaire}</p>}
                        {modeSelectionne.details.compte && <p>Compte / RIB : {modeSelectionne.details.compte}</p>}
                        {modeSelectionne.details.iban && <p>IBAN : {modeSelectionne.details.iban}</p>}
                        {modeSelectionne.details.bic && <p>BIC / SWIFT : {modeSelectionne.details.bic}</p>}
                      </>
                    )}
                    <p className="muted">Effectuez le transfert, puis indiquez sa référence ci-dessous. L’abonnement ne sera activé qu’après vérification.</p>
                  </div>
                )}
                <div className="form-grid">
                  <Field label="Référence de transaction" htmlFor="abonnement-reference" required hint="Référence fournie par Mobile Money ou par la banque.">
                    <Input id="abonnement-reference" value={reference} onChange={(e) => setReference(e.target.value)} maxLength={100} required />
                  </Field>
                  <Field label="Justificatif (facultatif)" htmlFor="abonnement-justificatif" hint="PDF, JPG ou PNG — 5 Mo maximum.">
                    <Input
                      id="abonnement-justificatif"
                      type="file"
                      accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                      onChange={(e) => {
                        const file = e.target.files?.[0] || null
                        if (file && file.size > 5 * 1024 * 1024) {
                          setErreur('Le justificatif ne doit pas dépasser 5 Mo.')
                          e.target.value = ''
                          setJustificatif(null)
                          return
                        }
                        setJustificatif(file)
                        setErreur(null)
                      }}
                    />
                  </Field>
                </div>
                {justificatif && <p className="field-hint">Fichier choisi : {justificatif.name}</p>}
                <div className="form-actions">
                  <button type="submit" className="btn btn--primary" disabled={envoi || demandeEnAttente || !mode}>
                    {envoi ? 'Envoi…' : 'Soumettre le paiement'}
                  </button>
                </div>
              </>
            ) : (
              <Alert type="error">
                Aucun numéro Mobile Money ni compte bancaire n’est configuré. Contactez la plateforme ; aucun paiement ni renouvellement ne sera enregistré tant que les coordonnées réelles ne seront pas définies.
              </Alert>
            )}
          </form>

          <div className="abonnement-en-ligne">
            <div>
              <strong>Paiement en ligne</strong>
              <p>{options.paiement_en_ligne.message}</p>
            </div>
            <span className="badge badge--neutral">Non configuré</span>
          </div>
        </div>
      </section>

      <section className="card abonnement-historique">
        <div className="card-header"><h2>Mes demandes</h2></div>
        <div className="card-body">
          {demandes.length === 0 ? (
            <p className="muted">Aucune demande de renouvellement.</p>
          ) : (
            <div className="table-wrapper">
              <table className="table">
                <thead><tr><th>Date</th><th>Formule</th><th>Montant</th><th>Mode</th><th>Référence</th><th>Statut</th><th>Justificatif</th></tr></thead>
                <tbody>
                  {demandes.map((d) => (
                    <tr key={d.id}>
                      <td>{new Date(d.created_at).toLocaleDateString('fr-FR')}</td>
                      <td>{options.formules.find((f) => f.code === d.plan)?.libelle || d.plan} · {d.duree_mois} mois</td>
                      <td>{Number(d.montant).toLocaleString('fr-FR')} {options.devise}</td>
                      <td>{d.mode_paiement === 'mobile_money' ? 'Mobile Money' : 'Virement'}</td>
                      <td>{d.reference}</td>
                      <td><span className={`badge ${d.statut === 'approuvee' ? 'badge--success' : d.statut === 'refusee' ? 'badge--danger' : 'badge--warning'}`}>{statutLabel[d.statut]}</span>{d.motif_refus && <p className="field-error">{d.motif_refus}</p>}</td>
                      <td>{d.a_justificatif ? 'Reçu' : '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </section>
      <p className="muted abonnement-contact">Compte : {user?.name}. Pour toute question, contactez l’administration de la plateforme.</p>
      <div className="abonnement-actions">
        <Link to="/" className="btn btn--ghost">Retour au tableau de bord</Link>
        <button type="button" className="btn btn--ghost" onClick={() => logout().then(() => navigate('/login'))}>Se déconnecter</button>
      </div>
    </div>
  )
}
