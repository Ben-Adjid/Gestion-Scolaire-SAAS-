import { Link } from 'react-router-dom'

// Formules : tarifs et fonctionnalités tirés de config/plans.php.
const FORMULES = [
  {
    nom: 'Standard',
    prix: '25 000',
    inclus: [
      'Élèves, classes et inscriptions',
      'Appels, absences et présences',
      'Notes et bulletins',
      'Emploi du temps et messagerie',
    ],
  },
  {
    nom: 'VIP',
    prix: '50 000',
    inclus: [
      'Tout le plan Standard',
      'Conseil de classe et procès-verbaux',
      'Relances d’impayés, reçus et export comptable',
      'Espace parents, notifications et cahier de texte',
      'Dossiers et classes d’examen',
    ],
    recommandee: true,
  },
  {
    nom: 'Entreprise',
    prix: '100 000',
    inclus: [
      'Tout le plan VIP',
      'Plusieurs sites sous un même groupe',
      'Personnalisation de l’espace et accès API',
      'Utilisateurs illimités et support prioritaire',
    ],
  },
]

const ESPACES = [
  { role: 'Direction', texte: 'Vue d’ensemble de l’établissement, validation des conseils et des résultats.' },
  { role: 'Enseignants', texte: 'Appels, notes, cahier de texte et emploi du temps de leurs classes uniquement.' },
  { role: 'Vie scolaire', texte: 'Absences, justifications et convocations.' },
  { role: 'Secrétariat et finances', texte: 'Inscriptions, frais scolaires, paiements et reçus.' },
  { role: 'Familles', texte: 'Présences, notes et messages concernant leurs enfants (formule VIP).' },
]

export default function Landing() {
  return (
    <div className="landing">
      <header className="landing-nav">
        <span className="landing-brand">Gestion scolaire</span>
        <nav className="landing-links">
          <a href="#formules">Formules</a>
          <a href="#espaces">Espaces</a>
          <Link to="/login" className="landing-btn landing-btn--small">Se connecter</Link>
        </nav>
      </header>

      <section className="landing-hero">
        <div>
          <h1>Gestion de l’établissement, dans un seul espace.</h1>
          <p className="landing-lead">
            Élèves, présences, notes, bulletins et finances. Chaque personne accède uniquement
            aux informations de son rôle.
          </p>
          <div className="landing-actions">
            <Link to="/login" className="landing-btn">Se connecter</Link>
            <a href="#formules" className="landing-btn landing-btn--ghost">Voir les formules</a>
          </div>
        </div>
      </section>

      <section id="formules" className="landing-section">
        <h2>Formules</h2>
        <p className="landing-lead">Tarifs mensuels en FCFA. Une formule s’active après un échange avec la plateforme.</p>
        <div className="landing-plans">
          {FORMULES.map((f) => (
            <article key={f.nom} className={`landing-plan ${f.recommandee ? 'landing-plan--vedette' : ''}`}>
              {f.recommandee && <span className="landing-plan-badge">La plus choisie</span>}
              <h3>{f.nom}</h3>
              <p className="landing-plan-prix">{f.prix} FCFA <span>/ mois</span></p>
              <ul>
                {f.inclus.map((i) => <li key={i}>{i}</li>)}
              </ul>
            </article>
          ))}
        </div>
      </section>

      <section id="espaces" className="landing-section">
        <h2>Espaces par rôle</h2>
        <div className="landing-grid">
          {ESPACES.map((e) => (
            <article key={e.role} className="landing-card">
              <h3>{e.role}</h3>
              <p>{e.texte}</p>
            </article>
          ))}
        </div>
      </section>

      <footer className="landing-footer">
        <span>Gestion scolaire</span>
        <Link to="/login">Connexion</Link>
      </footer>
    </div>
  )
}
