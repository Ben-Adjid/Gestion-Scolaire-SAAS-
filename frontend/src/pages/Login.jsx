import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { useEtablissement } from '../context/EtablissementContext'
import Alert from '../components/Alert'
import Spinner from '../components/Spinner'
import { Field, Input } from '../components/Field'

export default function Login() {
  const { login } = useAuth()
  const { etablissement } = useEtablissement()
  const navigate = useNavigate()

  const [form, setForm] = useState({ email: '', password: '' })
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)
  const [globalError, setGlobalError] = useState(null)
  const [code, setCode] = useState('')
  const [etape2fa, setEtape2fa] = useState(false)

  const handleChange = (e) => {
    const { name, value } = e.target
    setForm((prev) => ({ ...prev, [name]: value }))
    setErrors((prev) => ({ ...prev, [name]: undefined }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSubmitting(true)
    setGlobalError(null)

    try {
      const resultat = await login({
        ...form,
        email: form.email.trim(),
        ...(etape2fa ? { code: code.replace(/\s+/g, '') } : {}),
      })
      if (resultat?.requires2fa) {
        setEtape2fa(true)
        setGlobalError(null)
        return
      }
      navigate('/', { replace: true })
    } catch (err) {
      if (err.errors && Object.keys(err.errors).length) {
        setErrors(err.errors)
      } else {
        setGlobalError(err.message)
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="login-page">
      <div className="login-card">
        <div className="login-brand">
          {etablissement?.logo_path ? (
            <img src={`/${etablissement.logo_path}`} alt="Logo" className="login-logo-img" />
          ) : (
            <span className="login-logo" aria-hidden="true">{(etablissement?.nom || 'E').charAt(0).toUpperCase()}</span>
          )}
          <h1>{etablissement?.nom || 'Espace scolaire'}</h1>
          <p>Connectez-vous pour accéder à votre espace de gestion.</p>
        </div>

        {globalError && (
          <Alert onClose={() => setGlobalError(null)}>{globalError}</Alert>
        )}

        <form onSubmit={handleSubmit} noValidate>
          <Field label="Adresse e-mail" htmlFor="email" required error={errors.email?.[0]}>
            <Input
              id="email"
              type="email"
              name="email"
              value={form.email}
              onChange={handleChange}
              placeholder="vous@ecole.com"
              autoComplete="email"
              autoFocus
              error={errors.email?.[0]}
            />
          </Field>

          <Field label="Mot de passe" htmlFor="password" required error={errors.password?.[0]}>
            <Input
              id="password"
              type="password"
              name="password"
              value={form.password}
              onChange={handleChange}
              placeholder="••••••••"
              autoComplete="current-password"
              error={errors.password?.[0]}
            />
          </Field>

          {etape2fa && (
            <Field label="Code de vérification" htmlFor="code" required hint="Le code à six chiffres affiché par votre application d’authentification.">
              <Input
                id="code"
                name="code"
                inputMode="numeric"
                autoComplete="one-time-code"
                maxLength={10}
                value={code}
                onChange={(e) => setCode(e.target.value)}
                placeholder="123 456"
                autoFocus
              />
            </Field>
          )}

          <p className="login-demo" style={{ marginTop: 12, textAlign: 'right' }}>
            <Link to="/mot-de-passe-oublie">Mot de passe oublié ?</Link>
          </p>

          <button type="submit" className="btn btn--primary btn--block" disabled={submitting || (etape2fa && !code.trim())}>
            {submitting ? (
              <>
                <Spinner size="sm" /> Connexion…
              </>
            ) : etape2fa ? (
              'Valider le code'
            ) : (
              'Se connecter'
            )}
          </button>
        </form>

        {/* Identifiants de démonstration : affichés seulement en développement, jamais en production. */}
        {import.meta.env.DEV && (
        <div className="login-demo">
          <p>
            Identifiants de démonstration :<br />
            <strong>superadmin@test.com</strong> / <strong>password</strong> (Super Admin)<br />
            <strong>admin@test.com</strong> / <strong>password</strong> (Admin)<br />
            <strong>proviseur@test.com</strong> / <strong>password</strong> (Proviseur)<br />
            <strong>enseignant@test.com</strong> / <strong>password</strong> (Enseignant)
          </p>
        </div>
        )}
      </div>
    </div>
  )
}
