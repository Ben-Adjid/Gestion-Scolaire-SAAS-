import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { motDePasseApi } from '../api/endpoints'
import Alert from '../components/Alert'
import Spinner from '../components/Spinner'
import { Field, Input } from '../components/Field'

// Page ouverte depuis le lien reçu par e-mail : ?token=...&email=...
export default function Reinitialiser() {
  const [params] = useSearchParams()
  const [nouveau, setNouveau] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [envoi, setEnvoi] = useState(false)
  const [succes, setSucces] = useState(null)
  const [erreur, setErreur] = useState(null)

  const token = params.get('token') || ''
  const email = params.get('email') || ''
  const incomplet = !token || !email

  const soumettre = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    setErreur(null)
    try {
      const res = await motDePasseApi.reinitialiser({
        email, token, password: nouveau, password_confirmation: confirmation,
      })
      setSucces(res.data.message)
    } catch (err) {
      setErreur(err.errors?.password?.[0] || err.message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <div className="login-page">
      <div className="login-card">
        <div className="login-brand">
          <h1>Nouveau mot de passe</h1>
          <p>Au moins 8 caractères.</p>
        </div>

        {incomplet && <Alert>Ce lien est incomplet. Ouvrez le lien reçu par e-mail.</Alert>}
        {erreur && <Alert>{erreur}</Alert>}

        {succes ? (
          <>
            <Alert>{succes}</Alert>
            <p className="login-demo" style={{ marginTop: 16 }}><Link to="/login">Se connecter</Link></p>
          </>
        ) : (
          !incomplet && (
            <form onSubmit={soumettre} noValidate>
              <Field label="Nouveau mot de passe" htmlFor="nouveau" required>
                <Input id="nouveau" type="password" value={nouveau} onChange={(e) => setNouveau(e.target.value)} autoComplete="new-password" autoFocus />
              </Field>
              <Field label="Confirmer le mot de passe" htmlFor="confirmation" required>
                <Input id="confirmation" type="password" value={confirmation} onChange={(e) => setConfirmation(e.target.value)} autoComplete="new-password" />
              </Field>
              <button type="submit" className="btn btn--primary btn--block" disabled={envoi || nouveau.length < 8 || nouveau !== confirmation}>
                {envoi ? <><Spinner size="sm" /> Enregistrement…</> : 'Enregistrer'}
              </button>
            </form>
          )
        )}
      </div>
    </div>
  )
}
