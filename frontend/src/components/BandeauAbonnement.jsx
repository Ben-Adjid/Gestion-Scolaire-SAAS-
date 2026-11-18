import { Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { useEtablissement } from '../context/EtablissementContext'

// Bandeau persistant pour l'administrateur quand l'abonnement arrive à échéance (7 jours et moins).
export default function BandeauAbonnement() {
  const { user } = useAuth()
  const { etablissement } = useEtablissement()

  const jours = etablissement.jours_restants
  if (user?.role !== 'admin' || jours === null || jours === undefined || jours < 0 || jours > 7) return null

  const urgent = jours <= 3
  const delai = jours === 0 ? "aujourd’hui" : `dans ${jours} jour${jours > 1 ? 's' : ''}`

  return (
    <div className={`bandeau-abonnement ${urgent ? 'bandeau-abonnement--urgent' : ''}`} role="alert">
      <span>
        L’abonnement de <strong>{etablissement.nom}</strong> expire {delai}. Renouvelez-le pour éviter la suspension de l’espace.
      </span>
      <Link to="/abonnement" className="btn btn--ghost btn--sm">Renouveler</Link>
    </div>
  )
}
