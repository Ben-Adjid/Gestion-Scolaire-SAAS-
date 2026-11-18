import { useState } from 'react'
import { motDePasseApi } from '../api/endpoints'
import Alert from '../components/Alert'
import Spinner from '../components/Spinner'
import { Field, Input } from '../components/Field'
import { useAuth } from '../context/AuthContext'

// Affichée à la première connexion après un mot de passe temporaire (ou à la demande depuis la sécurité).
export default function ChangerMotDePasse() {
  const { user } = useAuth()
  const obligatoire = Boolean(user?.doit_changer_mot_de_passe)
  const [actuel, setActuel] = useState('')
  const [nouveau, setNouveau] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [envoi, setEnvoi] = useState(false)
  const [erreur, setErreur] = useState(null)

  const soumettre = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    setErreur(null)
    try {
      await motDePasseApi.changer({ actuel, nouveau, nouveau_confirmation: confirmation })
      // Rechargement : la session est relue et le blocage n'existe plus.
      window.location.assign('/')
    } catch (err) {
      setErreur(err.errors?.nouveau?.[0] || err.message)
      setEnvoi(false)
    }
  }

  return (
    <div className="login-page">
      <div className="login-card">
        <div className="login-brand">
          <h1>Changer le mot de passe</h1>
          <p>{obligatoire
            ? 'Votre mot de passe est temporaire. Choisissez-en un nouveau pour accéder à votre espace.'
            : 'Choisissez un nouveau mot de passe (au moins 8 caractères).'}</p>
        </div>
        {erreur && <Alert>{erreur}</Alert>}
        <form onSubmit={soumettre} noValidate>
          <Field label="Mot de passe actuel" htmlFor="actuel" required>
            <Input id="actuel" type="password" value={actuel} onChange={(e) => setActuel(e.target.value)} autoComplete="current-password" autoFocus />
          </Field>
          <Field label="Nouveau mot de passe" htmlFor="nouveau" required>
            <Input id="nouveau" type="password" value={nouveau} onChange={(e) => setNouveau(e.target.value)} autoComplete="new-password" />
          </Field>
          <Field label="Confirmer le nouveau mot de passe" htmlFor="confirmation" required>
            <Input id="confirmation" type="password" value={confirmation} onChange={(e) => setConfirmation(e.target.value)} autoComplete="new-password" />
          </Field>
          <button type="submit" className="btn btn--primary btn--block" disabled={envoi || !actuel || nouveau.length < 8 || nouveau !== confirmation}>
            {envoi ? <><Spinner size="sm" /> Enregistrement…</> : 'Enregistrer le nouveau mot de passe'}
          </button>
        </form>
      </div>
    </div>
  )
}
