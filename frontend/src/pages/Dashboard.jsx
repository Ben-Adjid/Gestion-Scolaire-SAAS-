import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { alertesApi, dashboardApi } from '../api/endpoints'
import Spinner from '../components/Spinner'
import Alert from '../components/Alert'
import { useAuth } from '../context/AuthContext'
import { mentionColor } from '../utils/format'
import { MEDALS } from '../utils/constants'

const TOUS_SAUF_IT = ['admin', 'proviseur', 'censeur', 'cpe', 'enseignant', 'secretaire', 'gestionnaire', 'agent_comptable']

const STAT_CARDS = [
  { key: 'etudiants', label: 'Élèves', icon: '🎓', color: 'indigo', to: '/etudiants', roles: TOUS_SAUF_IT },
  { key: 'classes', label: 'Classes', icon: '🏫', color: 'green', to: '/classes', roles: ['admin', 'proviseur', 'censeur', 'cpe', 'secretaire'] },
  { key: 'enseignants', label: 'Enseignants', icon: '👩‍🏫', color: 'amber', to: '/enseignants', roles: ['admin', 'proviseur', 'censeur'] },
  { key: 'matieres', label: 'Matières', icon: '📚', color: 'rose', to: '/matieres', roles: ['admin', 'proviseur', 'censeur', 'enseignant'] },
  { key: 'notes', label: 'Notes saisies', icon: '📝', color: 'cyan', to: '/notes', roles: ['admin', 'proviseur', 'censeur', 'enseignant'] },
]

const PEUT_VOIR_PALMARES = ['admin', 'proviseur', 'censeur', 'cpe']
const PEUT_VOIR_INSCRIPTIONS = ['admin', 'proviseur', 'secretaire']

export default function Dashboard() {
  const { user } = useAuth()
  const [data, setData] = useState(null)
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(true)
  const [alertes, setAlertes] = useState([])
  const [alertesErreur, setAlertesErreur] = useState(null)

  const peutVoirAlertes = ['super_admin', 'admin', 'proviseur', 'censeur', 'cpe'].includes(user?.role)

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
    dashboardApi
      .index()
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
  const masculinPct = totalGenre ? (repartition_par_genre.masculin / totalGenre) * 100 : 0
  const totalCycle = repartition_par_cycle.college + repartition_par_cycle.lycee

  const roleContent = {
    super_admin: {
      subtitle: "Bienvenue, Super Administrateur. Gerez la plateforme SaaS.",
      actions: (
        <>
          <Link to="/utilisateurs" className="btn btn--primary">Utilisateurs</Link>
          <Link to="/parametres" className="btn btn--ghost">Parametres</Link>
        </>
      ),
    },
    admin: {
      subtitle: "Bienvenue, Administrateur. Pilotez tout l’etablissement.",
      actions: (
        <>
          <Link to="/etudiants" className="btn btn--primary">+ Nouvel eleve</Link>
          <Link to="/palmares" className="btn btn--ghost">Tableau d’honneur</Link>
          <Link to="/bulletins" className="btn btn--ghost">Bulletins</Link>
        </>
      ),
    },
    proviseur: {
      subtitle: "Bienvenue, Proviseur. Pilotez tout l’etablissement.",
      actions: (
        <>
          <Link to="/etudiants" className="btn btn--primary">+ Nouvel eleve</Link>
          <Link to="/palmares" className="btn btn--ghost">Tableau d’honneur</Link>
          <Link to="/bulletins" className="btn btn--ghost">Bulletins</Link>
        </>
      ),
    },
    censeur: {
      subtitle: "Bienvenue, Censeur. Suivez les resultats et les merites.",
      actions: (
        <>
          <Link to="/palmares" className="btn btn--primary">Voir le palmares</Link>
          <Link to="/bulletins" className="btn btn--ghost">Consulter les bulletins</Link>
          <Link to="/presences" className="btn btn--ghost">Absences</Link>
        </>
      ),
    },
    cpe: {
      subtitle: "Bienvenue, CPE. Suivez les eleves et les absences.",
      actions: (
        <>
          <Link to="/presences" className="btn btn--primary">Absences</Link>
          <Link to="/eleves-difficulte" className="btn btn--ghost">Eleves en difficulte</Link>
          <Link to="/bulletins" className="btn btn--ghost">Bulletins</Link>
        </>
      ),
    },
    enseignant: {
      subtitle: "Bienvenue. Saisissez vos notes et consultez votre emploi du temps.",
      actions: (
        <>
          <Link to="/notes" className="btn btn--primary">Saisir des notes</Link>
          <Link to="/emploi-du-temps" className="btn btn--ghost">Emploi du temps</Link>
          <Link to="/pointer" className="btn btn--ghost">Pointer les absences</Link>
        </>
      ),
    },
    secretaire: {
      subtitle: "Bienvenue, Secretaire. Gerez les inscriptions et documents.",
      actions: (
        <>
          <Link to="/inscriptions" className="btn btn--primary">Inscriptions</Link>
          <Link to="/classes" className="btn btn--ghost">Classes</Link>
          <Link to="/etudiants" className="btn btn--ghost">Eleves</Link>
        </>
      ),
    },
    gestionnaire: {
      subtitle: "Bienvenue, Gestionnaire. Gerez les finances scolaires.",
      actions: (
        <>
          <Link to="/frais-scolaires" className="btn btn--primary">Frais scolaires</Link>
          <Link to="/etudiants" className="btn btn--ghost">Eleves</Link>
        </>
      ),
    },
    agent_comptable: {
      subtitle: "Bienvenue, Agent Comptable. Gerez les finances et paiements.",
      actions: (
        <>
          <Link to="/frais-scolaires" className="btn btn--primary">Frais scolaires</Link>
          <Link to="/etudiants" className="btn btn--ghost">Eleves</Link>
        </>
      ),
    },
    responsable_it: {
      subtitle: "Bienvenue, Responsable IT. Gerez l’infrastructure.",
      actions: (
        <>
          <Link to="/audits" className="btn btn--primary">Journal d’audit</Link>
          <Link to="/messages" className="btn btn--ghost">Messagerie</Link>
        </>
      ),
    },
  }

  const currentRole = roleContent[user?.role] || roleContent.admin

  return (
    <div className="dashboard">
      <div className="dashboard-hero">
        <div>
          <h1>Tableau de bord</h1>
          <p>{currentRole.subtitle}</p>
        </div>
        <div className="hero-actions">{currentRole.actions}</div>
      </div>

      {peutVoirAlertes && (
        <section className="card" style={{ marginBottom: 16 }}>
          <div className="card-header">
            <h2>⚠️ Alertes à vérifier</h2>
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

      <div className="stat-grid">
        {STAT_CARDS.filter((card) => user?.role === 'super_admin' || card.roles.includes(user?.role)).map((card) => (
          <Link key={card.key} to={card.to} className={`stat-card stat-card--${card.color}`}>
            <span className="stat-icon">{card.icon}</span>
            <div className="stat-body">
              <span className="stat-value">{stats[card.key]}</span>
              <span className="stat-label">{card.label}</span>
            </div>
          </Link>
        ))}
      </div>

      <div className="dashboard-grid">
        <section className="card">
          <div className="card-header">
            <h2>🏆 Top 5 des élèves</h2>
            {(user?.role === 'super_admin' || PEUT_VOIR_PALMARES.includes(user?.role)) && (
              <Link to="/palmares" className="card-link">
                Voir le palmarès
              </Link>
            )}
          </div>
          <div className="card-body">
            {top_eleves.length === 0 ? (
              <p className="muted">Aucune note saisie pour le moment.</p>
            ) : (
              <ul className="top-list">
                {top_eleves.map((eleve, index) => (
                  <li key={eleve.id} className="top-item">
                    <span className="top-rank">{MEDALS[index] || `${index + 1}ᵉ`}</span>
                    <span className="top-avatar">
                      {(eleve.prenom?.[0] || '') + (eleve.nom?.[0] || '')}
                    </span>
                    <div className="top-info">
                      <span className="top-name">
                        {eleve.prenom} {eleve.nom}
                      </span>
                      <span className="top-classe">{eleve.classe}</span>
                    </div>
                    <span className="top-moyenne">{Number(eleve.moyenne || 0).toFixed(2)}</span>
                    {eleve.mention && (
                      <span
                        className={`mention-badge mention-badge--${mentionColor(eleve.mention.key)}`}
                      >
                        {eleve.mention.label}
                      </span>
                    )}
                  </li>
                ))}
              </ul>
            )}
          </div>
        </section>

        <section className="card">
          <div className="card-header">
            <h2>Répartition des effectifs</h2>
          </div>
          <div className="card-body">
            <div className="cycle-bars">
              {totalCycle > 0 && (
                <>
                  <div className="cycle-row">
                    <span className="cycle-label">Collège</span>
                    <div className="bar-track">
                      <div
                        className="bar-fill bar-fill--green"
                        style={{ width: `${(repartition_par_cycle.college / totalCycle) * 100}%` }}
                      />
                    </div>
                    <span className="cycle-value">{repartition_par_cycle.college}</span>
                  </div>
                  <div className="cycle-row">
                    <span className="cycle-label">Lycée</span>
                    <div className="bar-track">
                      <div
                        className="bar-fill bar-fill--indigo"
                        style={{ width: `${(repartition_par_cycle.lycee / totalCycle) * 100}%` }}
                      />
                    </div>
                    <span className="cycle-value">{repartition_par_cycle.lycee}</span>
                  </div>
                </>
              )}
            </div>

            <h3 className="section-subtitle">Par genre</h3>
            {totalGenre === 0 ? (
              <p className="muted">Aucun élève enregistré.</p>
            ) : (
              <div className="genre-chart">
                <div
                  className="donut"
                  style={{
                    background: `conic-gradient(#2563eb ${masculinPct}%, #db2777 ${masculinPct}%)`,
                  }}
                >
                  <span className="donut-center">{totalGenre}</span>
                </div>
                <ul className="legend">
                  <li>
                    <span className="legend-dot legend-dot--masculin" />
                    Masculin : <strong>{repartition_par_genre.masculin}</strong>
                  </li>
                  <li>
                    <span className="legend-dot legend-dot--feminin" />
                    Féminin : <strong>{repartition_par_genre.feminin}</strong>
                  </li>
                </ul>
              </div>
            )}
          </div>
        </section>
      </div>

      <div className="dashboard-grid">
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
                      <div
                        className="bar-fill"
                        style={{ width: `${(classe.etudiants_count / maxClasse) * 100}%` }}
                      />
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
            {(user?.role === 'super_admin' || PEUT_VOIR_INSCRIPTIONS.includes(user?.role)) && (
              <Link to="/inscriptions" className="card-link">
                Inscriptions
              </Link>
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
                      <td className="cell-strong">
                        {etudiant.prenom} {etudiant.nom}
                      </td>
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
