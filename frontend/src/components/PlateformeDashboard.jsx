import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import client from '../api/client'
import Spinner from './Spinner'
import Alert from './Alert'

const formatFcfa = (montant) => `${Number(montant || 0).toLocaleString('fr-FR')} FCFA`

const STATUT = {
  actif: { label: 'Actif', badge: 'badge--success' },
  essai: { label: 'Essai', badge: 'badge--neutral' },
  suspendu: { label: 'Suspendu', badge: 'badge--amber' },
  expire: { label: 'Expiré', badge: 'badge--danger' },
}

const FORMULES = [
  { code: 'standard', label: 'Standard', prix: 25000 },
  { code: 'vip', label: 'VIP', prix: 50000 },
  { code: 'entreprise', label: 'Entreprise', prix: 100000 },
]

function Echeance({ jours }) {
  if (jours === null || jours === undefined) return <span className="muted">—</span>
  if (jours < 0) return <span className="badge badge--danger">Expiré depuis {Math.abs(jours)} j</span>
  if (jours <= 7) return <span className="badge badge--danger">{jours} j</span>
  if (jours <= 30) return <span className="badge badge--amber">{jours} j</span>
  return <span className="badge badge--neutral">{jours} j</span>
}

export default function PlateformeDashboard() {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    client
      .get('/super-admin/dashboard')
      .then((res) => setData(res.data))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false))
  }, [])

  if (loading) {
    return (
      <div className="page-loader">
        <Spinner />
      </div>
    )
  }

  if (error) return <Alert>{error}</Alert>

  const stats = data?.stats || {}
  const encaissements = data?.encaissements || []
  const renouvellements = data?.renouvellements || []
  const recents = data?.recents || []
  const parPlan = stats.par_plan || {}
  const maxEncaisse = Math.max(...encaissements.map((m) => m.montant), 1)
  const date = new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })

  const indicateurs = [
    { label: 'Établissements', value: stats.total_etablissements ?? 0, sub: `${stats.part_actifs ?? 0} % actifs` },
    { label: 'Revenus mensuels récurrents', value: formatFcfa(stats.mrr_fcfa), sub: 'Abonnements actifs' },
    { label: 'Encaissé sur 30 jours', value: formatFcfa(stats.encaisse_30j_fcfa), sub: 'Paiements enregistrés' },
    { label: 'Élèves inscrits', value: stats.total_eleves ?? 0, sub: `${stats.total_enseignants ?? 0} enseignants` },
  ]

  return (
    <div className="dashboard">
      <header className="dash-head">
        <div>
          <p className="dash-date">{date}</p>
          <h1 className="dash-title">Vue plateforme</h1>
          <p className="dash-sub">Abonnements, revenus et renouvellements de tous les établissements</p>
        </div>
        <nav className="dash-actions" aria-label="Raccourcis">
          <Link to="/super-admin" className="btn btn--primary">Gérer les écoles</Link>
          <Link to="/super-admin" className="btn btn--ghost">Support</Link>
        </nav>
      </header>

      <section className="dash-kpis" aria-label="Indicateurs plateforme">
        {indicateurs.map((i) => (
          <div key={i.label} className="dash-kpi">
            <span className="dash-kpi-value">{i.value}</span>
            <span className="dash-kpi-label">{i.label}</span>
            <span className="dash-kpi-sub">{i.sub}</span>
          </div>
        ))}
      </section>

      <div className="dash-grid">
        <section className="card">
          <div className="card-header">
            <h2>Encaissements, 12 derniers mois</h2>
          </div>
          <div className="card-body">
            <div className="plat-bars" role="img" aria-label="Montant encaissé par mois">
              {encaissements.map((m) => (
                <div key={m.mois} className="plat-bar">
                  <span className="plat-bar-value">{m.montant ? Math.round(m.montant / 1000) + ' k' : ''}</span>
                  <div className="plat-bar-track">
                    <div className="plat-bar-fill" style={{ height: `${(m.montant / maxEncaisse) * 100}%` }} />
                  </div>
                  <span className="plat-bar-label">{m.libelle}</span>
                </div>
              ))}
            </div>
          </div>
        </section>

        <section className="card">
          <div className="card-header">
            <h2>Répartition par formule</h2>
          </div>
          <div className="card-body">
            <ul className="dash-ranking">
              {FORMULES.map((f) => (
                <li key={f.code} className="dash-ranking-row">
                  <div className="dash-ranking-info">
                    <span className="top-name">{f.label}</span>
                    <span className="top-classe">{formatFcfa(f.prix)} / mois</span>
                  </div>
                  <strong className="top-moyenne">{parPlan[f.code] ?? 0}</strong>
                </li>
              ))}
            </ul>
          </div>
        </section>
      </div>

      <section className="card" style={{ marginBottom: 16 }}>
        <div className="card-header">
          <h2>Renouvellements à prévoir</h2>
          <span className="muted">Échéance dans 30 jours ou dépassée</span>
        </div>
        <div className="card-body">
          {renouvellements.length === 0 ? (
            <p className="muted">Aucun abonnement à renouveler dans les 30 prochains jours.</p>
          ) : (
            <table className="table">
              <thead>
                <tr>
                  <th>Établissement</th>
                  <th>Formule</th>
                  <th>Statut</th>
                  <th>Échéance</th>
                  <th>Reste</th>
                </tr>
              </thead>
              <tbody>
                {renouvellements.map((r) => (
                  <tr key={r.id}>
                    <td className="cell-strong">{r.nom}</td>
                    <td>{FORMULES.find((f) => f.code === r.plan)?.label || r.plan}</td>
                    <td>
                      <span className={`badge ${STATUT[r.statut_abonnement]?.badge || 'badge--neutral'}`}>
                        {STATUT[r.statut_abonnement]?.label || r.statut_abonnement}
                      </span>
                    </td>
                    <td className="cell-muted">{r.abonnement_fin}</td>
                    <td><Echeance jours={r.jours_restants} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </section>

      <section className="card">
        <div className="card-header">
          <h2>Derniers établissements</h2>
        </div>
        <div className="card-body">
          {recents.length === 0 ? (
            <p className="muted">Aucun établissement pour le moment.</p>
          ) : (
            <table className="table">
              <thead>
                <tr>
                  <th>Établissement</th>
                  <th>Formule</th>
                  <th>Élèves</th>
                  <th>Créé le</th>
                </tr>
              </thead>
              <tbody>
                {recents.map((e) => (
                  <tr key={e.id}>
                    <td className="cell-strong">{e.nom}</td>
                    <td>{FORMULES.find((f) => f.code === e.plan)?.label || e.plan}</td>
                    <td>{e.etudiants_count}</td>
                    <td className="cell-muted">{e.created_at}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </section>
    </div>
  )
}
