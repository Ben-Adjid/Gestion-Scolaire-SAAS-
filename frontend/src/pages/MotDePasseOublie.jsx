import { useState } from 'react'
import { Link } from 'react-router-dom'
import { motDePasseApi } from '../api/endpoints'
import Alert from '../components/Alert'
import Spinner from '../components/Spinner'
import { Field, Input } from '../components/Field'

export default function MotDePasseOublie() {
  const [email, setEmail] = useState('')
  const [envoi, setEnvoi] = useState(false)
  const [message, setMessage] = useState(null)
  const [erreur, setErreur] = useState(null)

  const soumettre = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    setErreur(null)
    try {
      const res = await motDePasseApi.oublie(email.trim())
      setMessage(res.data.message)
    } catch (err) {
      setErreur(err.message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <div className="login-page">
      <div className="login-card">
        <div className="login-brand">
          <h1>Mot de passe oublié</h1>
          <p>Indiquez votre adresse e-mail. Vous recevrez un lien pour choisir un nouveau mot de passe.</p>
        </div>

        {erreur && <Alert>{erreur}</Alert>}
        {message ? (
          <Alert>{message}</Alert>
        ) : (
          <form onSubmit={soumettre} noValidate>
            <Field label="Adresse e-mail" htmlFor="email" required>
              <Input id="email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} autoComplete="email" autoFocus />
            </Field>
            <button type="submit" className="btn btn--primary btn--block" disabled={envoi || !email.trim()}>
              {envoi ? <><Spinner size="sm" /> Envoi…</> : 'Envoyer le lien'}
            </button>
          </form>
        )}

        <p className="login-demo" style={{ marginTop: 16 }}>
          <Link to="/login">Retour à la connexion</Link>
        </p>
      </div>
    </div>
  )
}
