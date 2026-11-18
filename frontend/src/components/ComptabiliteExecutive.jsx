import { useEffect, useState } from 'react'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'
import { useEtablissement } from '../context/EtablissementContext'
import { aLaFonctionnalite } from '../utils/plans'
import { PremiumBadge } from './PlanGate'

const ROLES_COMPTABILITE = ['admin', 'proviseur', 'gestionnaire', 'agent_comptable']
const RAYON = 54
const CIRCONFERENCE = 2 * Math.PI * RAYON

const montant = (v) => `${Math.round(Number(v) || 0).toLocaleString('fr-FR')} FCFA`

// Anneau SVG : part de la somme encaissée par rapport au total facturé.
function Anneau({ taux }) {
  const part = Math.max(0, Math.min(100, taux ?? 0))
  return (
    <svg viewBox="0 0 140 140" className="anneau" role="img" aria-label={`Recouvrement ${part} %`}>
      <circle cx="70" cy="70" r={RAYON} className="anneau-fond" />
      <circle
        cx="70"
        cy="70"
        r={RAYON}
        className="anneau-part"
        strokeDasharray={`${(part / 100) * CIRCONFERENCE} ${CIRCONFERENCE}`}
        transform="rotate(-90 70 70)"
      />
      <text x="70" y="78" textAnchor="middle" className="anneau-texte">{part}%</text>
    </svg>
  )
}

// Executive dashboard (Entreprise) : frais perçus, impayés, encaissements et répartition par classe.
export default function ComptabiliteExecutive() {
  const { user } = useAuth()
  const { etablissement } = useEtablissement()
  const [synthese, setSynthese] = useState(null)

  const roleConcerne = ROLES_COMPTABILITE.includes(user?.role)
  const ouvert = aLaFonctionnalite(user, etablissement, 'comptabilite_avancee')

  useEffect(() => {
    if (roleConcerne && ouvert) {
      client.get('/comptabilite/synthese').then((res) => setSynthese(res.data)).catch(() => setSynthese(null))
    }
  }, [roleConcerne, ouvert])

  if (!roleConcerne) return null

  if (!ouvert) {
    return (
      <section className="card resume-verrouille" style={{ marginBottom: 16 }}>
        <div className="card-body">
          <strong>Comptabilité avancée</strong> <PremiumBadge feature="comptabilite_avancee" />
          <p className="muted">Frais perçus, impayés et encaissements dans le temps, en temps réel.</p>
        </div>
      </section>
    )
  }

  if (!synthese) return null

  const { kpis, encaissements_par_mois: mois, par_classe: classes } = synthese
  const maxMois = Math.max(1, ...mois.map((m) => m.total))

  return (
    <section className="executive" style={{ marginBottom: 16 }}>
      <div className="executive-entete">
        <div>
          <span className="executive-badge">Executive</span>
          <h2>Finances en temps réel</h2>
          <button
            type="button"
            className="btn btn--ghost btn--sm"
            onClick={() => client.get('/comptabilite/export', { responseType: 'blob' })
              .then((res) => telecharger(res.data, 'comptabilite-recouvrement.xlsx'))
              .catch(() => {})}
          >
            Exporter (Excel)
          </button>
        </div>
      </div>

      <div className="executive-grille">
        <div className="executive-carte executive-anneau">
          <Anneau taux={kpis.taux_recouvrement} />
          <span className="executive-label">Taux de recouvrement</span>
        </div>

        <div className="executive-kpis">
          <div className="executive-kpi">
            <span className="executive-label">Frais facturés</span>
            <strong>{montant(kpis.facture)}</strong>
          </div>
          <div className="executive-kpi executive-kpi--ok">
            <span className="executive-label">Perçus</span>
            <strong>{montant(kpis.encaisse)}</strong>
          </div>
          <div className="executive-kpi executive-kpi--alerte">
            <span className="executive-label">Impayés</span>
            <strong>{montant(kpis.impaye)}</strong>
          </div>
        </div>

        <div className="executive-carte executive-mois">
          <span className="executive-label">Encaissements, 12 derniers mois</span>
          <div className="executive-colonnes">
            {mois.length === 0 && <span className="muted">Aucun encaissement.</span>}
            {mois.map((m) => (
              <div key={m.mois} className="executive-colonne" title={montant(m.total)}>
                <div className="executive-colonne-barre" style={{ height: `${(m.total / maxMois) * 100}%` }} />
                <span>{m.mois.slice(5)}</span>
              </div>
            ))}
          </div>
        </div>

        <div className="executive-carte executive-classes">
          <span className="executive-label">Par classe</span>
          {classes.length === 0 && <span className="muted">Aucun frais enregistré.</span>}
          {classes.map((c) => {
            const part = c.facture > 0 ? (c.encaisse / c.facture) * 100 : 0
            return (
              <div key={c.classe} className="executive-classe">
                <span>{c.classe}</span>
                <div className="executive-piste"><div style={{ width: `${part}%` }} /></div>
                <strong className={c.impaye > 0 ? 'cell-danger' : ''}>{montant(c.impaye)}</strong>
              </div>
            )
          })}
        </div>
      </div>
    </section>
  )
}
