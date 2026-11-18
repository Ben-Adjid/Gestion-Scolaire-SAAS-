import { Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import Abonnement from './Abonnement'

// Page de blocage : affichée quand l'abonnement de l'établissement n'est plus actif.
// Les données sont conservées ; l'accès revient dès que le paiement est validé.
export default function AbonnementSuspendu() {
  const { user, loading, logout } = useAuth()

  if (loading) return <div className="page-loader"><span>Chargement…</span></div>
  if (user?.role === 'admin') return <Abonnement />

  return (
    <div className="suspension-page">
      <div className="suspension-carte">
        <h1>Espace suspendu</h1>
        <p>
          L’abonnement de votre établissement n’est plus actif. Vos données sont conservées et seront
          de nouveau accessibles dès le renouvellement.
        </p>
        <p className="muted">Connectez-vous avec le compte administrateur de l’établissement pour consulter les modes de paiement et soumettre une demande de renouvellement.</p>
        <div className="suspension-actions">
          {!user && <Link className="btn btn--primary" to="/login">Se connecter</Link>}
          <button type="button" className="btn btn--ghost" onClick={() => logout().then(() => window.location.assign('/login'))}>
            Se déconnecter
          </button>
        </div>
      </div>
    </div>
  )
}
