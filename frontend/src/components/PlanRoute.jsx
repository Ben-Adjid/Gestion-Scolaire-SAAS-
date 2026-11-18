import { useAuth } from '../context/AuthContext'
import { useEtablissement } from '../context/EtablissementContext'
import { aLaFonctionnalite } from '../utils/plans'
import { UpgradePrompt } from './PlanGate'
import Spinner from './Spinner'

// Protège une page par formule : la page n'est rendue que si la formule l'inclut.
export default function PlanRoute({ feature, children }) {
  const { user } = useAuth()
  const { etablissement } = useEtablissement()

  // Tant que la formule n'est pas chargée, on n'affiche rien pour éviter un faux refus.
  if (user?.role !== 'super_admin' && !etablissement.fonctionnalites) return <Spinner />

  if (aLaFonctionnalite(user, etablissement, feature)) return children

  return <UpgradePrompt feature={feature} />
}
