import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { alertesApi, dashboardApi } from '../api/endpoints'
import Spinner from '../components/Spinner'
import Alert from '../components/Alert'
import ResumeAnalyses from '../components/ResumeAnalyses'
import ComptabiliteExecutive from '../components/ComptabiliteExecutive'
import PresenceDuJour from '../components/PresenceDuJour'
import FilResume from '../components/FilResume'
import ATraiterCard from '../components/ATraiterCard'
import PlateformeDashboard from '../components/PlateformeDashboard'
import { useAuth } from '../context/AuthContext'
import { useEtablissement } from '../context/EtablissementContext'
import { mentionColor } from '../utils/format'
import { aLaFonctionnalite } from '../utils/plans'

const TOUS_SAUF_IT = ['admin', 'proviseur', 'censeur', 'cpe', 'surveillant', 'enseignant', 'secretaire', 'gestionnaire', 'agent_comptable']

const INDICATEURS = [
  { key: 'etudiants', label: 'Élèves', to: '/etudiants', roles: TOUS_SAUF_IT },
  { key: 'classes', label: 'Classes', to: '/classes', roles: ['admin', 'proviseur', 'censeur', 'cpe', 'secretaire'] },
  { key: 'enseignants', label: 'Enseignants', to: '/enseignants', roles: ['admin', 'proviseur', 'censeur'] },
  { key: 'matieres', label: 'Matières', to: '/matieres', roles: ['admin', 'proviseur', 'censeur', 'enseignant'] },
  { key: 'notes', label: 'Notes saisies', to: '/notes', roles: ['admin', 'proviseur', 'censeur', 'enseignant'] },
]

const PEUT_VOIR_PALMARES = ['admin', 'proviseur', 'censeur', 'cpe']
const PEUT_VOIR_INSCRIPTIONS = ['admin', 'proviseur', 'secretaire']

const ACTIONS_PAR_ROLE = {
  super_admin: [
    { to: '/utilisateurs', label: 'Utilisateurs', principal: true },
    { to: '/parametres', label: 'Paramètres' },
  ],
  admin: [
    { to: '/etudiants', label: 'Nouvel élève', principal: true },
    { to: '/palmares', label: 'Tableau d’honneur' },
    { to: '/bulletins', label: 'Bulletins' },
  ],
  proviseur: [
    { to: '/etudiants', label: 'Nouvel élève', principal: true },
    { to: '/palmares', label: 'Tableau d’honneur' },
    { to: '/bulletins', label: 'Bulletins' },
  ],
  censeur: [
    { to: '/palmares', label: 'Tableau d’honneur', principal: true },
    { to: '/bulletins', label: 'Bulletins' },
    { to: '/presences', label: 'Absences' },
  ],
  cpe: [
    { to: '/presences', label: 'Absences', principal: true },
    { to: '/eleves-difficulte', label: 'Élèves en difficulté' },
    { to: '/bulletins', label: 'Bulletins' },
  ],
  surveillant: [
    { to: '/appels', label: 'Faire l’appel', principal: true },
    { to: '/presences', label: 'Absences' },
    { to: '/emploi-du-temps', label: 'Emploi du temps' },
  ],
  enseignant: [
    { to: '/notes', label: 'Saisir des notes', principal: true },
    { to: '/emploi-du-temps', label: 'Emploi du temps' },
    { to: '/pointer', label: 'Pointer' },
  ],
  secretaire: [
    { to: '/inscriptions', label: 'Inscriptions', principal: true },
    { to: '/classes', label: 'Classes' },
    { to: '/etudiants', label: 'Élèves' },
  ],
  gestionnaire: [
    { to: '/frais-scolaires', label: 'Frais scolaires', principal: true },
    { to: '/etudiants', label: 'Élèves' },
  ],
  agent_comptable: [
    { to: '/frais-scolaires', label: 'Frais scolaires', principal: true },
    { to: '/etudiants', label: 'Élèves' },
  ],
  responsable_it: [
    { to: '/audits', label: 'Journal d’audit', principal: true },
    { to: '/messages', label: 'Messagerie' },
  ],
}

const SOUS_TITRE_PAR_ROLE = {
  super_admin: 'Vue plateforme',
  admin: 'Vue de l’établissement',
  proviseur: 'Vue de l’établissement',
  censeur: 'Résultats et discipline',
  cpe: 'Vie scolaire',
  surveillant: 'Surveillance et appels',
  enseignant: 'Vos classes et vos notes',
  secretaire: 'Inscriptions et dossiers',
  gestionnaire: 'Finances scolaires',
  agent_comptable: 'Finances et paiements',
  responsable_it: 'Infrastructure et accès',
}

export default function Dashboard() {
  const { user } = useAuth()
  const { etablissement } = useEtablissement()
  const [data, setData] = useState(null)
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(true)
  const [alertes, setAlertes] = useState([])
  const [alertesErreur, setAlertesErreur] = useState(null)

  // Les alertes sont des données scolaires : la plateforme (super admin) ne les consulte pas.
  const peutVoirAlertes = ['admin', 'proviseur', 'censeur', 'cpe'].includes(user?.role)
    && aLaFonctionnalite(user, etablissement, 'alertes')

  const chargerAlertes = () => {
    alertesApi
      .list()
      .then((res) => setAlertes(res.data.data))
      .catch((err) => setAlertesErreur(err.message))
  }

  useEffect(() => {
    if (peutVoirAlertes) chargerAlertes()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [peutVoirAlertes])

  const verifierAlerte = (id) => {
    alertesApi
      .verifier(id)
      .then(() => setAlertes((liste) => liste.filter((a) => a.id !== id)))
      .catch((err) => setAlertesErreur(err.message))
  }

  useEffect(() => {
    // Le super admin n'a pas d'établissement : il a sa propre vue plateforme.
    if (user?.role === 'super_admin') return
    dashboardApi
      .index()
      .then((res) => setData(res.data))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false))
  }, [user?.role])

  if (user?.role === 'super_admin') {
    return <PlateformeDashboard />
  }

  if (loading) {
    return (
      <div className="page-loader">
        <Spinner />
      </div>
    )
  }

  if (error) {
    return <Alert>{error}</Alert>
  }

  const {
    stats = {},
    repartition_par_classe = [],
    repartition_par_genre = { masculin: 0, feminin: 0 },
    repartition_par_cycle = { college: 0, lycee: 0 },
    top_eleves = [],
    derniers_etudiants = [],
  } = data || {}
  const maxClasse = Math.max(...repartition_par_classe.map((c) => c.etudiants_count), 1)
  const totalGenre = repartition_par_genre.masculin + repartition_par_genre.feminin
  const totalCycle = repartition_par_cycle.college + repartition_par_cycle.lycee

  const role = user?.role || 'admin'
  const actions = ACTIONS_PAR_ROLE[role] || ACTIONS_PAR_ROLE.admin
  const indicateurs = INDICATEURS.filter((i) => role === 'super_admin' || i.roles.includes(role))
  const date = new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })

  return (
    <div className="dashboard">
      <header className="dash-head">
        <div>
          <p className="dash-date">{date}</p>
          <h1 className="dash-title">Tableau de bord</h1>
          <p className="dash-sub">{SOUS_TITRE_PAR_ROLE[role] || 'Vue d’ensemble'}</p>
        </div>
        <nav className="dash-actions" aria-label="Raccourcis">
          {actions.map((a) => (
            <Link key={a.to} to={a.to} className={`btn ${a.principal ? 'btn--primary' : 'btn--ghost'}`}>
              {a.label}
            </Link>
          ))}
        </nav>
      </header>

      {indicateurs.length > 0 && (
        <section className="dash-kpis" aria-label="Chiffres clés">
          {indicateurs.map((i) => (
            <Link key={i.key} to={i.to} className="dash-kpi">
              <span className="dash-kpi-value">{stats[i.key] ?? 0}</span>
              <span className="dash-kpi-label">{i.label}</span>
            </Link>
          ))}
        </section>
      )}

      {['admin', 'proviseur', 'censeur', 'cpe'].includes(user?.role) && <ATraiterCard />}
      <PresenceDuJour />
      <FilResume />
      <ComptabiliteExecutive />
      <ResumeAnalyses />

      {peutVoirAlertes && (
        <section className="card" style={{ marginBottom: 16 }}>
          <div className="card-header">
            <h2>Alertes à vérifier</h2>
          </div>
          <div className="card-body">
            {alertesErreur && <Alert>{alertesErreur}</Alert>}
            {!alertesErreur && alertes.length === 0 && <p className="muted">Aucune alerte en attente.</p>}
            {alertes.length > 0 && (
              <ul className="top-list">
                {alertes.map((alerte) => (
                  <li key={alerte.id} className="top-item">
                    <span className={`badge ${alerte.severity === 'critical' ? 'badge--danger' : 'badge--amber'}`}>
                      {alerte.severity === 'critical' ? 'Critique' : 'Attention'}
                    </span>
                    <div className="top-info">
                      <span className="top-name">
                        {alerte.etudiant ? `${alerte.etudiant.prenom} ${alerte.etudiant.nom}` : 'Élève supprimé'}
                      </span>
                      <span className="top-classe">{alerte.message}</span>
                    </div>
                    <button type="button" className="btn btn--ghost btn--sm" onClick={() => verifierAlerte(alerte.id)}>
                      Marquer vérifiée
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </section>
      )}

      <div className="dash-grid">
        <section className="card">
          <div className="card-header">
            <h2>Meilleurs élèves</h2>
            {(role === 'super_admin' || PEUT_VOIR_PALMARES.includes(role)) && (
              <Link to="/palmares" className="card-link">Palmarès</Link>
            )}
          </div>
          <div className="card-body">
            {top_eleves.length === 0 ? (
              <p className="muted">Aucune note saisie pour le moment.</p>
            ) : (
              <ol className="dash-ranking">
                {top_eleves.map((eleve, index) => (
                  <li key={eleve.id} className="dash-ranking-row">
                    <span className="dash-ranking-rank">{index === 0 ? '1er' : `${index + 1}e`}</span>
                    <div className="dash-ranking-info">
                      <span className="top-name">{eleve.prenom} {eleve.nom}</span>
                      <span className="top-classe">{eleve.classe}</span>
                    </div>
                    <span className="top-moyenne">{Number(eleve.moyenne || 0).toFixed(2)}</span>
                    {eleve.mention && (
                      <span className={`mention-badge mention-badge--${mentionColor(eleve.mention.key)}`}>
                        {eleve.mention.label}
                      </span>
                    )}
                  </li>
                ))}
              </ol>
            )}
          </div>
        </section>

        <section className="card">
          <div className="card-header">
            <h2>Effectifs</h2>
          </div>
          <div className="card-body">
            {totalGenre === 0 && totalCycle === 0 ? (
              <p className="muted">Aucun élève enregistré.</p>
            ) : (
              <>
                <h3 className="section-subtitle">Par cycle</h3>
                <div className="dash-split">
                  <div className="dash-split-row">
                    <span>Collège</span>
                    <strong>{repartition_par_cycle.college}</strong>
                  </div>
                  <div className="dash-split-row">
                    <span>Lycée</span>
                    <strong>{repartition_par_cycle.lycee}</strong>
                  </div>
                </div>
                <h3 className="section-subtitle">Par genre</h3>
                <div className="dash-split">
                  <div className="dash-split-row">
                    <span>Garçons</span>
                    <strong>{repartition_par_genre.masculin}</strong>
                  </div>
                  <div className="dash-split-row">
                    <span>Filles</span>
                    <strong>{repartition_par_genre.feminin}</strong>
                  </div>
                </div>
              </>
            )}
          </div>
        </section>
      </div>

      <div className="dash-grid">
        <section className="card">
          <div className="card-header">
            <h2>Élèves par classe</h2>
          </div>
          <div className="card-body">
            {repartition_par_classe.length === 0 ? (
              <p className="muted">Aucune classe enregistrée.</p>
            ) : (
              <ul className="bar-list">
                {repartition_par_classe.map((classe) => (
                  <li key={classe.id} className="bar-row">
                    <span className="bar-label">{classe.nom}</span>
                    <div className="bar-track">
                      <div className="bar-fill" style={{ width: `${(classe.etudiants_count / maxClasse) * 100}%` }} />
                    </div>
                    <span className="bar-value">{classe.etudiants_count}</span>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </section>

        <section className="card">
          <div className="card-header">
            <h2>Derniers élèves inscrits</h2>
            {(role === 'super_admin' || PEUT_VOIR_INSCRIPTIONS.includes(role)) && (
              <Link to="/inscriptions" className="card-link">Inscriptions</Link>
            )}
          </div>
          <div className="card-body">
            {derniers_etudiants.length === 0 ? (
              <p className="muted">Aucun élève pour le moment.</p>
            ) : (
              <table className="table">
                <thead>
                  <tr>
                    <th>Nom</th>
                    <th>Classe</th>
                    <th>Matricule</th>
                  </tr>
                </thead>
                <tbody>
                  {derniers_etudiants.map((etudiant) => (
                    <tr key={etudiant.id}>
                      <td className="cell-strong">{etudiant.prenom} {etudiant.nom}</td>
                      <td>{etudiant.classe?.nom || '—'}</td>
                      <td className="cell-muted">{etudiant.matricule || '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </section>
      </div>
    </div>
  )
}
