import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { analysesApi } from '../api/endpoints'
import { useAuth } from '../context/AuthContext'
import { useEtablissement } from '../context/EtablissementContext'
import { aLaFonctionnalite } from '../utils/plans'
import { PremiumBadge } from './PlanGate'

const ROLES_ANALYSES = ['admin', 'proviseur', 'censeur']
const LARGEUR = 320
const HAUTEUR = 120

// Courbe des moyennes générales par trimestre (échelle 0 à 20).
function CourbeTrimestres({ points }) {
  if (points.length === 0) return <p className="muted">Les moyennes par trimestre apparaîtront avec les notes.</p>

  const pas = points.length > 1 ? LARGEUR / (points.length - 1) : 0
  const coords = points.map((p, i) => [i * pas + 8, HAUTEUR - (p.moyenne / 20) * (HAUTEUR - 16) - 8])
  const ligne = coords.map(([x, y]) => `${x},${y}`).join(' ')

  return (
    <svg viewBox={`0 0 ${LARGEUR + 16} ${HAUTEUR}`} className="courbe" role="img" aria-label="Moyennes par trimestre">
      <polyline points={ligne} className="courbe-ligne" fill="none" />
      {coords.map(([x, y], i) => (
        <g key={i}>
          <circle cx={x} cy={y} r="5" className="courbe-point" />
          <text x={x} y={HAUTEUR - 1} textAnchor="middle" className="courbe-legende">T{points[i].trimestre}</text>
        </g>
      ))}
    </svg>
  )
}

// Carte du tableau de bord : résumé des analyses (VIP et plus), ou carte verrouillée pour les autres formules.
export default function ResumeAnalyses() {
  const { user } = useAuth()
  const { etablissement } = useEtablissement()
  const [donnees, setDonnees] = useState(null)

  const roleConcerne = ROLES_ANALYSES.includes(user?.role)
  const ouvert = aLaFonctionnalite(user, etablissement, 'analyses_avancees')

  useEffect(() => {
    if (roleConcerne && ouvert) {
      analysesApi.index().then((res) => setDonnees(res.data)).catch(() => setDonnees(null))
    }
  }, [roleConcerne, ouvert])

  if (!roleConcerne) return null

  if (!ouvert) {
    return (
      <section className="card resume-verrouille" style={{ marginBottom: 16 }}>
        <div className="card-body">
          <div>
            <strong>Analyses avancées</strong> <PremiumBadge feature="analyses_avancees" />
          </div>
          <p className="muted">Moyennes par classe, courbe des performances, absences et taux de recouvrement.</p>
        </div>
      </section>
    )
  }

  if (!donnees) return null

  const { indicateurs, moyennes_par_trimestre: trimestres } = donnees
  return (
    <section className="card" style={{ marginBottom: 16 }}>
      <div className="card-header resume-entete">
        <h2>Analyses avancées</h2>
        <Link to="/analyses" className="btn btn--ghost btn--sm">Voir le détail</Link>
      </div>
      <div className="card-body resume-corps">
        <div className="analyses-indicateurs">
          <div className="analyse-kpi">
            <span className="analyse-kpi-valeur">{indicateurs.moyenne_generale ?? '—'}</span>
            <span className="analyse-kpi-label">Moyenne générale /20</span>
          </div>
          <div className="analyse-kpi">
            <span className="analyse-kpi-valeur">{indicateurs.taux_recouvrement !== null ? `${indicateurs.taux_recouvrement} %` : '—'}</span>
            <span className="analyse-kpi-label">Recouvrement</span>
          </div>
          <div className="analyse-kpi">
            <span className="analyse-kpi-valeur">{indicateurs.absences_total}</span>
            <span className="analyse-kpi-label">Absences</span>
          </div>
        </div>
        <div className="courbe-bloc">
          <span className="executive-label">Performance par trimestre</span>
          <CourbeTrimestres points={trimestres || []} />
        </div>
      </div>
    </section>
  )
}
