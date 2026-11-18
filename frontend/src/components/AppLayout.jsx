import { useEffect, useState } from 'react'
import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { useEtablissement } from '../context/EtablissementContext'
import { ROLE_LABELS } from '../utils/constants'
import { aLaFonctionnalite } from '../utils/plans'
import Assistant from './Assistant'
import RechercheGlobale from './RechercheGlobale'
import SynchroHorsLigne from './SynchroHorsLigne'
import BandeauAbonnement from './BandeauAbonnement'
import SupportModal from './SupportModal'
import { appliquerTheme } from '../utils/themes'

const NAV_SECTIONS = [
  {
    label: 'Principal',
    items: [
      { to: '/', label: 'Tableau de bord', icon: 'home', end: true, roles: ['super_admin', 'admin', 'proviseur', 'censeur', 'cpe', 'surveillant', 'enseignant', 'secretaire', 'gestionnaire', 'agent_comptable', 'responsable_it'] },
      { to: '/messages', label: 'Messagerie', icon: 'mail', roles: ['super_admin', 'admin', 'proviseur', 'censeur', 'cpe', 'surveillant', 'enseignant', 'secretaire', 'gestionnaire', 'agent_comptable', 'responsable_it'] },
      { to: '/espace-parent', label: 'Mes enfants', icon: 'student', roles: ['parent'], feature: 'espace_parent' },
      { to: '/chatboard', label: 'Échanges avec l’administration', icon: 'mail', roles: ['parent'], feature: 'espace_parent' },
      { to: '/mes-sorties', label: 'Autorisations de sortie', icon: 'clipboard', roles: ['parent'], feature: 'espace_parent' },
      { to: '/mes-notifications', label: 'Mes notifications', icon: 'settings', roles: ['parent'], feature: 'espace_parent' },
    ],
  },
  {
    label: 'Gestion Scolaire',
    items: [
      { to: '/etudiants', label: 'Élèves', icon: 'student', roles: ['super_admin', 'admin', 'proviseur', 'censeur', 'cpe', 'surveillant', 'enseignant', 'secretaire', 'gestionnaire', 'agent_comptable'] },
      { to: '/classes', label: 'Classes', icon: 'school', roles: ['super_admin', 'admin', 'proviseur', 'censeur', 'cpe', 'secretaire'] },
      { to: '/inscriptions', label: 'Inscriptions', icon: 'clipboard', roles: ['super_admin', 'admin', 'proviseur', 'secretaire'] },
      { to: '/enseignants', label: 'Enseignants', icon: 'users', roles: ['super_admin', 'admin', 'proviseur', 'censeur'] },
      { to: '/matieres', label: 'Matières', icon: 'book', roles: ['super_admin', 'admin', 'proviseur', 'censeur'] },
      { to: '/calendrier', label: 'Calendrier scolaire', icon: 'calendar', roles: ['super_admin', 'admin', 'proviseur', 'censeur', 'cpe', 'surveillant', 'enseignant', 'secretaire', 'gestionnaire', 'agent_comptable', 'responsable_it', 'parent'], feature: 'calendrier_scolaire' },
      { to: '/planning-examens', label: 'Planning des examens', icon: 'calendar', roles: ['super_admin', 'admin', 'proviseur', 'censeur', 'cpe', 'surveillant', 'enseignant', 'secretaire'], feature: 'planning_examens' },
      { to: '/actualites', label: 'Fil d’actualité', icon: 'mail', roles: ['super_admin', 'admin', 'proviseur', 'censeur', 'cpe', 'surveillant', 'enseignant', 'secretaire', 'gestionnaire', 'agent_comptable', 'responsable_it', 'parent'], feature: 'fil_actualite' },
      { to: '/cahier-texte', label: 'Cahier de texte', icon: 'book', roles: ['super_admin', 'admin', 'proviseur', 'censeur', 'enseignant', 'parent'], feature: 'cahier_texte' },
      { to: '/donnees', label: 'Import & export', icon: 'folder', roles: ['super_admin', 'admin', 'proviseur', 'secretaire'] },
      { to: '/justifications', label: 'Justifications', icon: 'clipboard', roles: ['super_admin', 'admin', 'proviseur', 'censeur', 'cpe'], feature: 'espace_parent' },
      { to: '/autorisations-sortie', label: 'Sorties signées', icon: 'clipboard', roles: ['super_admin', 'admin', 'proviseur', 'censeur', 'cpe', 'surveillant', 'secretaire'], feature: 'espace_parent' },
    ],
  },
  {
    label: 'Emploi du Temps & Présences',
    items: [
      { to: '/emploi-du-temps', label: 'Emploi du temps', icon: 'calendar', roles: ['super_admin', 'admin', 'proviseur', 'censeur', 'cpe', 'surveillant', 'enseignant', 'secretaire'] },
      { to: '/pointer', label: 'Pointer', icon: 'check', roles: ['enseignant'] },
      { to: '/appels', label: 'Faire l’appel', icon: 'check', roles: ['enseignant', 'admin', 'proviseur', 'censeur', 'cpe', 'surveillant', 'secretaire'] },
      { to: '/presences', label: 'Présences & Absences', icon: 'check', roles: ['super_admin', 'admin', 'proviseur', 'censeur', 'cpe', 'surveillant', 'secretaire'] },
      { to: '/presence-enseignants', label: 'Présence des enseignants', icon: 'clock', roles: ['super_admin', 'admin', 'proviseur', 'censeur'] },
    ],
  },
  {
    label: 'Finances',
    items: [
      { to: '/frais-scolaires', label: 'Frais scolaires', icon: 'cash', roles: ['super_admin', 'admin', 'proviseur', 'gestionnaire', 'agent_comptable'] },
      { to: '/depenses', label: 'Dépenses & résultat', icon: 'cash', roles: ['super_admin', 'admin', 'proviseur', 'gestionnaire', 'agent_comptable'], feature: 'comptabilite_avancee' },
    ],
  },
  {
    label: 'Évaluations & Résultats',
    items: [
      { to: '/notes', label: 'Notes', icon: 'pen', roles: ['super_admin', 'admin', 'proviseur', 'enseignant', 'censeur'] },
      { to: '/bulletins', label: 'Bulletins', icon: 'report', roles: ['super_admin', 'admin', 'proviseur', 'censeur', 'cpe'] },
      { to: '/palmares', label: "Tableau d'honneur", icon: 'trophy', roles: ['super_admin', 'admin', 'proviseur', 'censeur', 'cpe'] },
      { to: '/conseil-classe', label: 'Conseil de classe', icon: 'school', roles: ['super_admin', 'admin', 'proviseur', 'censeur'], feature: 'conseil_classe' },
      { to: '/examens', label: "Classes d'examen", icon: 'exam', roles: ['super_admin', 'admin', 'proviseur', 'censeur'], feature: 'classes_examen' },
      { to: '/dossiers-examen', label: "Dossiers d'examen", icon: 'folder', roles: ['super_admin', 'admin', 'proviseur', 'censeur', 'cpe', 'secretaire'], feature: 'dossiers_examen' },
      { to: '/eleves-difficulte', label: 'Élèves en difficulté', icon: 'alert', roles: ['super_admin', 'admin', 'proviseur', 'censeur', 'cpe'] },
    ],
  },
  {
    label: 'Administration',
    items: [
      { to: '/utilisateurs', label: 'Utilisateurs', icon: 'users', roles: ['super_admin', 'admin', 'proviseur'] },
      { to: '/audits', label: "Journal d'audit", icon: 'clock', roles: ['super_admin', 'admin', 'responsable_it'], feature: 'journal_export' },
      { to: '/analyses', label: 'Analyses avancées', icon: 'report', roles: ['super_admin', 'admin', 'proviseur', 'censeur'], feature: 'analyses_avancees' },
      { to: '/archives', label: 'Archives annuelles', icon: 'folder', roles: ['super_admin', 'admin'], feature: 'archivage' },
      { to: '/groupe', label: 'Groupe scolaire', icon: 'school', roles: ['super_admin', 'admin'], feature: 'multi_sites' },
      { to: '/securite', label: 'Sécurité du compte', icon: 'settings', roles: ['super_admin', 'admin', 'proviseur', 'censeur', 'cpe', 'surveillant', 'enseignant', 'secretaire', 'gestionnaire', 'agent_comptable', 'responsable_it'] },
      { to: '/personnalisation', label: 'Personnalisation', icon: 'settings', roles: ['super_admin', 'admin'], feature: 'personnalisation' },
      { to: '/acces-api', label: 'Accès API', icon: 'settings', roles: ['super_admin', 'admin'], feature: 'api' },
      { to: '/support', label: 'Support prioritaire', icon: 'mail', roles: ['super_admin', 'admin', 'proviseur'], feature: 'support_prioritaire' },
      { to: '/parametres', label: 'Règles de gestion', icon: 'settings', roles: ['super_admin', 'admin', 'proviseur'] },
    ],
  },
  {
    label: 'Plateforme SaaS',
    items: [
      { to: '/super-admin', label: 'Écoles', icon: 'school', roles: ['super_admin'], end: true },
      { to: '/super-admin/abonnements', label: 'Abonnements', icon: 'cash', roles: ['super_admin'] },
      { to: '/super-admin/groupes', label: 'Groupes scolaires', icon: 'school', roles: ['super_admin'] },
      { to: '/super-admin/annonces', label: 'Annonces', icon: 'mail', roles: ['super_admin'] },
      { to: '/super-admin/support', label: 'Support', icon: 'clipboard', roles: ['super_admin'] },
      { to: '/super-admin/risque', label: 'Écoles à risque', icon: 'alert', roles: ['super_admin'] },
      { to: '/super-admin/journal', label: 'Journal', icon: 'clock', roles: ['super_admin'] },
      { to: '/super-admin/erreurs', label: 'Erreurs serveur', icon: 'alert', roles: ['super_admin'] },
    ],
  },
]

function Icon({ name }) {
  const paths = {
    home: (
      <>
        <path d="M3 10.5 12 3l9 7.5" />
        <path d="M5 9.5V21h14V9.5" />
      </>
    ),
    school: (
      <>
        <path d="m3 9 9-6 9 6" />
        <path d="M5 8v10M19 8v10" />
        <path d="M9 21V11h6v10" />
      </>
    ),
    users: (
      <>
        <circle cx="9" cy="8" r="3.5" />
        <path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6" />
        <path d="M16 5.5a3 3 0 0 1 0 5.8M17 14.5c2.3.6 4 2.6 4 5.5" />
      </>
    ),
    student: (
      <>
        <circle cx="12" cy="8" r="4" />
        <path d="M4 21c0-4 3.6-7 8-7s8 3 8 7" />
      </>
    ),
    clipboard: (
      <>
        <rect x="5" y="4" width="14" height="17" rx="2" />
        <path d="M9 4V2h6v2" />
        <path d="M9 10h6M9 14h6M9 18h3" />
      </>
    ),
    book: (
      <>
        <path d="M4 5a2 2 0 0 1 2-2h14v18H6a2 2 0 0 1-2-2z" />
        <path d="M4 17a2 2 0 0 1 2-2h14" />
      </>
    ),
    pen: (
      <>
        <path d="M12 20h9" />
        <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z" />
      </>
    ),
    report: (
      <>
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
        <path d="M14 2v6h6" />
        <path d="M8 13h8M8 17h5" />
      </>
    ),
    trophy: (
      <>
        <path d="M8 21h8" />
        <path d="M12 17v4" />
        <path d="M7 4h10v4a5 5 0 0 1-10 0z" />
        <path d="M7 6H4v1a4 4 0 0 0 4 4M17 6h3v1a4 4 0 0 1-4 4" />
      </>
    ),
    calendar: (
      <>
        <rect x="3" y="4" width="18" height="18" rx="2" />
        <path d="M16 2v4M8 2v4M3 10h18" />
      </>
    ),
    settings: (
      <>
        <circle cx="12" cy="12" r="3" />
        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
      </>
    ),
    clock: (
      <>
        <circle cx="12" cy="12" r="9" />
        <path d="M12 7v5l3 3" />
      </>
    ),
    alert: (
      <>
        <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" />
        <path d="M12 9v4M12 17h.01" />
      </>
    ),
    exam: (
      <>
        <path d="M22 10 12 5 2 10l10 5 10-5z" />
        <path d="M6 12v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5" />
      </>
    ),
    folder: (
      <>
        <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
      </>
    ),
    cash: (
      <>
        <rect x="2" y="6" width="20" height="12" rx="2" />
        <circle cx="12" cy="12" r="2.5" />
        <path d="M6 12h.01M18 12h.01" />
      </>
    ),
    check: (
      <>
        <path d="M9 11l3 3 8-8" />
        <path d="M20 12v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9" />
      </>
    ),
    mail: (
      <>
        <rect x="2" y="4" width="20" height="16" rx="2" />
        <path d="m2 7 10 6 10-6" />
      </>
    ),
  }

  return (
    <svg
      className="nav-icon"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      {paths[name]}
    </svg>
  )
}

export default function AppLayout() {
  const { user, logout } = useAuth()
  const { etablissement } = useEtablissement()
  const navigate = useNavigate()
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const [supportOuvert, setSupportOuvert] = useState(false)
  const [menuOuvert, setMenuOuvert] = useState(false)

  // L'assistant consulte les données de gestion de l'établissement : il n'est pas proposé aux parents ni à la plateforme.
  const assistantDisponible = !['parent', 'super_admin'].includes(user?.role)

  // Chaque type de compte a son ambiance visuelle ; le thème par défaut revient à la sortie.
  useEffect(() => appliquerTheme(user?.role), [user?.role])

  const handleLogout = async () => {
    await logout()
    navigate('/login')
  }

  const initials = (user?.name || 'A')
    .split(' ')
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()

  // La plateforme ne voit que son tableau de bord et la gestion des écoles : jamais les pages métier d'une école.
  const estSuperAdmin = user?.role === 'super_admin'
  const visibleSections = NAV_SECTIONS
    .map((section) => ({
      ...section,
      items: section.items.filter((item) => {
        if (estSuperAdmin) return item.to === '/' || section.label === 'Plateforme SaaS'
        return (!item.roles || item.roles.includes(user?.role)) && aLaFonctionnalite(user, etablissement, item.feature)
      }),
    }))
    .filter((section) => section.items.length > 0)

  return (
    <div className="app-shell">
      <aside className={`sidebar ${sidebarOpen ? 'sidebar--open' : ''}`}>
        <div className="sidebar-brand">
          {etablissement.logo_path ? (
            <img src={`/${etablissement.logo_path}`} alt="" className="brand-logo-img" />
          ) : (
            <span className="brand-logo" aria-hidden="true">{(etablissement.nom || 'E').charAt(0).toUpperCase()}</span>
          )}
          <div className="brand-texts">
            <span className="brand-text">{etablissement.nom}</span>
            <span className="brand-subtitle">
              {estSuperAdmin ? 'Plateforme' : etablissement.annee_scolaire ? `Année ${etablissement.annee_scolaire}` : ''}
            </span>
          </div>
        </div>
        <nav className="sidebar-nav">
          {visibleSections.map((section) => (
            <div key={section.label} className="nav-section">
              <span className="nav-section-label">{section.label}</span>
              {section.items.map((item) => (
                <NavLink
                  key={item.to}
                  to={item.to}
                  end={item.end}
                  className={({ isActive }) =>
                    `nav-link ${isActive ? 'nav-link--active' : ''}`
                  }
                  onClick={() => setSidebarOpen(false)}
                >
                  <Icon name={item.icon} />
                  <span>{item.label}</span>
                </NavLink>
              ))}
            </div>
          ))}
        </nav>
        <div className="sidebar-footer">
          <button type="button" className="nav-link nav-link--logout" onClick={handleLogout}>
            <svg
              className="nav-icon"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.8"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
              <path d="M16 17l5-5-5-5" />
              <path d="M21 12H9" />
            </svg>
            <span>Déconnexion</span></button>
        </div>
      </aside>

      {sidebarOpen && (
        <button
          type="button"
          className="sidebar-backdrop"
          onClick={() => setSidebarOpen(false)}
          aria-label="Fermer le menu"
        />
      )}

      <div className="main">
        <header className="topbar">
          <button
            type="button"
            className="hamburger"
            onClick={() => setSidebarOpen((open) => !open)}
            aria-label="Ouvrir le menu"
          >
            <span />
            <span />
            <span /></button>
          <div className="topbar-title">{etablissement.nom}</div>
          {!['parent', 'super_admin'].includes(user?.role) && <RechercheGlobale />}
          <div className="topbar-actions">
            {assistantDisponible && (
              <button
                type="button"
                className="topbar-action topbar-action--ghost"
                onClick={() => window.dispatchEvent(new Event('ouvrir-assistant'))}
              >
                Assistant
              </button>
            )}
            {!estSuperAdmin && (
              <button type="button" className="topbar-action" onClick={() => setSupportOuvert(true)}>
                Support
              </button>
            )}
          </div>
          <div className="topbar-user">
            <button
              type="button"
              className="avatar-bouton"
              onClick={() => setMenuOuvert((ouvert) => !ouvert)}
              aria-expanded={menuOuvert}
              aria-label="Menu du compte"
            >
              <span className="avatar avatar--theme">{initials}</span></button>
            {menuOuvert && (
              <div className="menu-compte" role="menu">
                <div className="menu-compte-entete">
                  <strong>{user?.name}</strong>
                  <span className="muted">{ROLE_LABELS[user?.role] || user?.role}</span>
                </div>
                <button
                  type="button"
                  role="menuitem"
                  className="menu-compte-item"
                  onClick={() => {
                    setMenuOuvert(false)
                    setSupportOuvert(true)
                  }}
                >
                  Contacter le support
                </button>
                <button type="button" role="menuitem" className="menu-compte-item menu-compte-item--danger" onClick={handleLogout}>
                  Se déconnecter
                </button>
              </div>
            )}
          </div>
        </header>
        <main className="content">
          <BandeauAbonnement />
          {!['parent', 'super_admin'].includes(user?.role) && <SynchroHorsLigne />}
          <Outlet />
        </main>
      </div>
      <SupportModal open={supportOuvert} onClose={() => setSupportOuvert(false)} />
      {assistantDisponible && <Assistant />}
    </div>
  )
}
