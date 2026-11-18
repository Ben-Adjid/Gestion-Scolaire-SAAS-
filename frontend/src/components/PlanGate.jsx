import { Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { useEtablissement } from '../context/EtablissementContext'
import { aLaFonctionnalite, formuleMinimale } from '../utils/plans'

// Pastille « VIP » indiquant la formule minimale requise.
export function PremiumBadge({ feature }) {
  const { etablissement } = useEtablissement()
  const libelle = formuleMinimale(feature, etablissement) || 'Premium'
  return <span className="badge badge--premium">{libelle}</span>
}

// Bouton d'action : actif si la formule l'inclut, sinon désactivé avec un badge Premium.
export function FeatureButton({ feature, className = 'btn btn--ghost', onClick, children }) {
  const { user } = useAuth()
  const { etablissement } = useEtablissement()

  if (aLaFonctionnalite(user, etablissement, feature)) {
    return (
      <button type="button" className={className} onClick={onClick}>
        {children}
      </button>
    )
  }

  return (
    <button type="button" className={`${className} btn--locked`} disabled title="Incluse dans une formule supérieure">
      {children} <PremiumBadge feature={feature} /></button>
  )
}

// Écran affiché à la place d'une page réservée à une formule supérieure.
export function UpgradePrompt({ feature }) {
  const { etablissement } = useEtablissement()
  const libelle = formuleMinimale(feature, etablissement)

  return (
    <div className="upgrade-prompt">
      <h2>Fonctionnalité {libelle ? `réservée à la formule ${libelle}` : 'non incluse'}</h2>
      <p>
        Votre formule actuelle, <strong>{etablissement.plan_label || 'Standard'}</strong>, ne donne pas accès à cette page.
        Contactez la plateforme pour passer à une formule supérieure.
      </p>
      <Link to="/" className="btn btn--primary">Retour au tableau de bord</Link>
    </div>
  )
}
