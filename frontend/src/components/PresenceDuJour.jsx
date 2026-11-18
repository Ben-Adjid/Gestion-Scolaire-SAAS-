import { useEffect, useState } from 'react'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'
import { useEtablissement } from '../context/EtablissementContext'
import { aLaFonctionnalite } from '../utils/plans'
import { PremiumBadge } from './PlanGate'

const ROLES = ['admin', 'proviseur', 'censeur', 'cpe', 'secretaire']
const INTERVALLE_MS = 60000

// Présence du jour en temps réel : absents et retards, par classe (formule VIP et plus).
export default function PresenceDuJour() {
  const { user } = useAuth()
  const { etablissement } = useEtablissement()
  const [donnees, setDonnees] = useState(null)
  const roleConcerne = ROLES.includes(user?.role)
  const ouvert = aLaFonctionnalite(user, etablissement, 'presence_temps_reel')

  useEffect(() => {
    if (!roleConcerne || !ouvert) return undefined
    const charger = () => client.get('/presence-du-jour').then((res) => setDonnees(res.data)).catch(() => {})
    charger()
    const timer = setInterval(charger, INTERVALLE_MS)
    return () => clearInterval(timer)
  }, [roleConcerne, ouvert])

  if (!roleConcerne) return null

  if (!ouvert) {
    return (
      <section className="card resume-verrouille" style={{ marginBottom: 16 }}>
        <div className="card-body">
          <strong>Présence du jour</strong> <PremiumBadge feature="presence_temps_reel" />
          <p className="muted">Absents et retards en temps réel, classe par classe.</p>
        </div>
      </section>
    )
  }

  if (!donnees) return null

  return (
    <section className="card presence-jour" style={{ marginBottom: 16 }}>
      <div className="card-header resume-entete">
        <h2>Présence du jour</h2>
        <span className="muted">Actualisé toutes les minutes</span>
      </div>
      <div className="card-body">
        <div className="analyses-indicateurs">
          <div className="analyse-kpi">
            <span className="analyse-kpi-valeur">{donnees.total_eleves}</span>
            <span className="analyse-kpi-label">Élèves inscrits</span>
          </div>
          <div className="analyse-kpi">
            <span className="analyse-kpi-valeur analyse-kpi-valeur--alerte">{donnees.absents}</span>
            <span className="analyse-kpi-label">Absents</span>
          </div>
          <div className="analyse-kpi">
            <span className="analyse-kpi-valeur">{donnees.retards}</span>
            <span className="analyse-kpi-label">Retards</span>
          </div>
        </div>
        <div className="presence-classes">
          {donnees.classes.map((c) => (
            <div key={c.id} className={`presence-classe ${c.absents + c.retards > 0 ? 'presence-classe--alerte' : ''}`}>
              <strong>{c.nom}</strong>
              <span>{c.absents} absent(s) · {c.retards} retard(s)</span>
              <small className="muted">sur {c.effectif} élève(s)</small>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}
