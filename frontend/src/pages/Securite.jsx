import { useEffect, useState } from 'react'
import client from '../api/client'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import Alert from '../components/Alert'
import { Field, Input } from '../components/Field'
import { useToast } from '../components/Toast'

// Sécurité du compte : double authentification par application (Google Authenticator, Microsoft, Authy…).
export default function Securite() {
  const toast = useToast()
  const [actif, setActif] = useState(null)
  const [attente, setAttente] = useState(null)
  const [code, setCode] = useState('')
  const [envoi, setEnvoi] = useState(false)
  const [erreur, setErreur] = useState(null)

  const charger = () => client.get('/2fa/statut').then((res) => setActif(res.data.actif)).catch((e) => setErreur(e.message))

  useEffect(() => {
    charger()
  }, [])

  const demarrer = async () => {
    setEnvoi(true)
    try {
      const res = await client.post('/2fa/activer')
      setAttente(res.data)
      setCode('')
    } catch (err) {
      toast.error(err.message)
    } finally {
      setEnvoi(false)
    }
  }

  const confirmer = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    try {
      await client.post('/2fa/confirmer', { code: code.replace(/\s+/g, '') })
      toast.success('Double authentification activée.')
      setAttente(null)
      setCode('')
      charger()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setEnvoi(false)
    }
  }

  const desactiver = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    try {
      await client.post('/2fa/desactiver', { code: code.replace(/\s+/g, '') })
      toast.success('Double authentification désactivée.')
      setCode('')
      charger()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setEnvoi(false)
    }
  }

  if (actif === null && !erreur) return <Spinner />

  return (
    <div>
      <PageHeader title="Sécurité du compte" subtitle="Ajoutez une vérification par code pour protéger votre accès." />
      {erreur && <Alert>{erreur}</Alert>}

      {actif === false && !attente && (
        <section className="card form-carte">
          <div className="card-body">
            <section className="form-section">
              <h3>Double authentification</h3>
              <p className="form-section-aide">
                Après votre mot de passe, l’application vous demandera un code à six chiffres, renouvelé toutes les 30 secondes.
              </p>
            </section>
            <div className="form-actions">
              <button type="button" className="btn btn--primary" onClick={demarrer} disabled={envoi}>
                {envoi ? 'Préparation…' : 'Activer la double authentification'}
              </button>
            </div>
          </div>
        </section>
      )}

      {attente && (
        <form className="card form-carte" onSubmit={confirmer}>
          <div className="card-body">
            <section className="form-section">
              <h3>1. Ajoutez le compte à votre application</h3>
              <p className="form-section-aide">Dans l’application, choisissez « saisir une clé » et entrez ce code secret :</p>
              <code className="cle-valeur">{attente.secret}</code>
            </section>
            <section className="form-section">
              <h3>2. Saisissez le code affiché</h3>
              <Field label="Code à six chiffres" htmlFor="2fa-code" required>
                <Input id="2fa-code" inputMode="numeric" maxLength={10} value={code} onChange={(e) => setCode(e.target.value)} placeholder="123 456" />
              </Field>
            </section>
            <div className="form-actions">
              <button type="button" className="btn btn--ghost" onClick={() => setAttente(null)}>Annuler</button>
              <button type="submit" className="btn btn--primary" disabled={envoi || !code.trim()}>Confirmer</button>
            </div>
          </div>
        </form>
      )}

      {actif === true && (
        <form className="card form-carte" onSubmit={desactiver}>
          <div className="card-body">
            <section className="form-section">
              <h3>Double authentification active</h3>
              <p className="form-section-aide">Pour la désactiver, saisissez un code valide de votre application.</p>
              <Field label="Code à six chiffres" htmlFor="2fa-desactiver" required>
                <Input id="2fa-desactiver" inputMode="numeric" maxLength={10} value={code} onChange={(e) => setCode(e.target.value)} />
              </Field>
            </section>
            <div className="form-actions">
              <button type="submit" className="btn btn--ghost" disabled={envoi || !code.trim()}>Désactiver</button>
            </div>
          </div>
        </form>
      )}
    </div>
  )
}
