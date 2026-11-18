import { Navigate, Route, Routes, useLocation, useParams } from 'react-router-dom'
import MotDePasseOublie from './pages/MotDePasseOublie'
import Reinitialiser from './pages/Reinitialiser'
import ChangerMotDePasse from './pages/ChangerMotDePasse'
import { useAuth } from './context/AuthContext'
import AppLayout from './components/AppLayout'
import Login from './pages/Login'
import Landing from './pages/Landing'
import EspaceParent from './pages/EspaceParent'
import Dashboard from './pages/Dashboard'
import Classes from './pages/Classes'
import Enseignants from './pages/Enseignants'
import Etudiants from './pages/Etudiants'
import EtudiantSuivi from './pages/EtudiantSuivi'
import Matieres from './pages/Matieres'
import Notes from './pages/Notes'
import Inscriptions from './pages/Inscriptions'
import Bulletins from './pages/Bulletins'
import Palmares from './pages/Palmares'
import ConseilClasse from './pages/ConseilClasse'
import Utilisateurs from './pages/Utilisateurs'
import EmploiDuTemps from './pages/EmploiDuTemps'
import Parametres from './pages/Parametres'
import ElevesDifficulte from './pages/ElevesDifficulte'
import ClassesExamen from './pages/ClassesExamen'
import DossiersExamen from './pages/DossiersExamen'
import Audits from './pages/Audits'
import FraisScolaires from './pages/FraisScolaires'
import Pointer from './pages/Pointer'
import Presences from './pages/Presences'
import PresenceEnseignants from './pages/PresenceEnseignants'
import Messages from './pages/Messages'
import SuperAdmin from './pages/SuperAdmin'
import Spinner from './components/Spinner'
import PlanRoute from './components/PlanRoute'
import Justifications from './pages/Justifications'
import Analyses from './pages/Analyses'
import Personnalisation from './pages/Personnalisation'
import AccesApi from './pages/AccesApi'
import Support from './pages/Support'
import Donnees from './pages/Donnees'
import Chatboard from './pages/Chatboard'
import Actualites from './pages/Actualites'
import PlanningExamens from './pages/PlanningExamens'
import Securite from './pages/Securite'
import Depenses from './pages/Depenses'
import Appels from './pages/Appels'
import Calendrier from './pages/Calendrier'
import Archives from './pages/Archives'
import Groupe from './pages/Groupe'
import CahierTexte from './pages/CahierTexte'
import AbonnementSuspendu from './pages/AbonnementSuspendu'
import Abonnement from './pages/Abonnement'

function ProtectedRoute({ children }) {
  const { user, loading } = useAuth()
  const { pathname } = useLocation()

  if (loading) {
    return (
      <div className="page-loader">
        <Spinner />
      </div>
    )
  }

  if (!user) {
    return <Navigate to="/accueil" replace />
  }

  // Mot de passe temporaire : seule la page de changement est accessible.
  if (user.doit_changer_mot_de_passe && pathname !== '/changer-mot-de-passe') {
    return <Navigate to="/changer-mot-de-passe" replace />
  }

  return children
}

function RoleRoute({ roles, children }) {
  const { user } = useAuth()

  if (roles && user?.role !== 'super_admin' && !roles.includes(user?.role)) {
    return <Navigate to="/" replace />
  }

  return children
}

// Sections de la plateforme : /super-admin (écoles), /super-admin/abonnements, /groupes, etc.
function SuperAdminRoute() {
  const { section } = useParams()
  const sections = ['abonnements', 'groupes', 'annonces', 'support', 'journal', 'risque']
  return <SuperAdmin section={sections.includes(section) ? section : 'ecoles'} />
}

function HomeRedirect() {
  const { user } = useAuth()

  if (user?.role === 'parent') return <Navigate to="/espace-parent" replace />

  // Le super admin arrive sur la vue plateforme (c'est le composant Dashboard qui la choisit).
  return <Dashboard />
}

export default function App() {
  return (
    <Routes>
      <Route path="/accueil" element={<Landing />} />
      <Route path="/login" element={<Login />} />
      <Route path="/mot-de-passe-oublie" element={<MotDePasseOublie />} />
      <Route path="/reinitialiser" element={<Reinitialiser />} />
      <Route
        path="/changer-mot-de-passe"
        element={
          <ProtectedRoute>
            <ChangerMotDePasse />
          </ProtectedRoute>
        }
      />
      <Route path="/abonnement-suspendu" element={<AbonnementSuspendu />} />
      <Route
        path="/abonnement"
        element={
          <ProtectedRoute>
            <RoleRoute roles={['admin']}>
              <Abonnement />
            </RoleRoute>
          </ProtectedRoute>
        }
      />

      <Route
        path="/"
        element={
          <ProtectedRoute>
            <AppLayout />
          </ProtectedRoute>
        }
      >
        <Route index element={<HomeRedirect />} />
        <Route
          path="classes"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'censeur', 'cpe', 'secretaire']}>
              <Classes />
            </RoleRoute>
          }
        />
        <Route
          path="enseignants"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'censeur']}>
              <Enseignants />
            </RoleRoute>
          }
        />
        <Route
          path="etudiants"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'censeur', 'cpe', 'enseignant', 'secretaire', 'gestionnaire', 'agent_comptable']}>
              <Etudiants />
            </RoleRoute>
          }
        />
        <Route
          path="etudiants/:id/suivi"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'censeur', 'cpe']}>
              <PlanRoute feature="fiche_suivi">
                <EtudiantSuivi />
              </PlanRoute>
            </RoleRoute>
          }
        />
        <Route
          path="matieres"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'censeur', 'enseignant']}>
              <Matieres />
            </RoleRoute>
          }
        />
        <Route
          path="notes"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'censeur', 'enseignant']}>
              <Notes />
            </RoleRoute>
          }
        />
        <Route
          path="inscriptions"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'secretaire']}>
              <Inscriptions />
            </RoleRoute>
          }
        />
        <Route
          path="bulletins"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'censeur', 'cpe']}>
              <Bulletins />
            </RoleRoute>
          }
        />
        <Route
          path="espace-parent"
          element={
            <RoleRoute roles={['parent']}>
              <PlanRoute feature="espace_parent">
                <EspaceParent />
              </PlanRoute>
            </RoleRoute>
          }
        />
        <Route
          path="conseil-classe"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'censeur']}>
              <PlanRoute feature="conseil_classe">
                <ConseilClasse />
              </PlanRoute>
            </RoleRoute>
          }
        />
        <Route
          path="palmares"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'censeur', 'cpe']}>
              <Palmares />
            </RoleRoute>
          }
        />
        <Route
          path="utilisateurs"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'super_admin']}>
              <Utilisateurs />
            </RoleRoute>
          }
        />
        <Route
          path="emploi-du-temps"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'censeur', 'enseignant', 'cpe', 'secretaire']}>
              <EmploiDuTemps />
            </RoleRoute>
          }
        />
        <Route
          path="parametres"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'super_admin']}>
              <Parametres />
            </RoleRoute>
          }
        />
        <Route
          path="presences"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'censeur', 'cpe', 'enseignant', 'secretaire']}>
              <Presences />
            </RoleRoute>
          }
        />
        <Route
          path="pointer"
          element={
            <RoleRoute roles={['enseignant']}>
              <Pointer />
            </RoleRoute>
          }
        />
        <Route
          path="presence-enseignants"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'censeur']}>
              <PresenceEnseignants />
            </RoleRoute>
          }
        />
        <Route
          path="messages"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'censeur', 'cpe', 'enseignant', 'secretaire', 'gestionnaire', 'agent_comptable', 'responsable_it', 'super_admin']}>
              <Messages />
            </RoleRoute>
          }
        />
        <Route
          path="frais-scolaires"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'gestionnaire', 'agent_comptable']}>
              <FraisScolaires />
            </RoleRoute>
          }
        />
        <Route
          path="eleves-difficulte"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'censeur', 'cpe']}>
              <ElevesDifficulte />
            </RoleRoute>
          }
        />
        <Route
          path="examens"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'censeur', 'enseignant']}>
              <PlanRoute feature="classes_examen">
                <ClassesExamen />
              </PlanRoute>
            </RoleRoute>
          }
        />
        <Route
          path="dossiers-examen"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'censeur', 'cpe', 'secretaire']}>
              <PlanRoute feature="dossiers_examen">
                <DossiersExamen />
              </PlanRoute>
            </RoleRoute>
          }
        />
        <Route
          path="appels"
          element={
            <RoleRoute roles={['enseignant', 'admin', 'proviseur', 'censeur', 'cpe', 'secretaire']}>
              <Appels />
            </RoleRoute>
          }
        />
        <Route
          path="depenses"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'gestionnaire', 'agent_comptable']}>
              <PlanRoute feature="comptabilite_avancee">
                <Depenses />
              </PlanRoute>
            </RoleRoute>
          }
        />
        <Route
          path="securite"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'censeur', 'cpe', 'enseignant', 'secretaire', 'gestionnaire', 'agent_comptable', 'responsable_it', 'parent']}>
              <Securite />
            </RoleRoute>
          }
        />
        <Route
          path="calendrier"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'censeur', 'cpe', 'enseignant', 'secretaire', 'gestionnaire', 'agent_comptable', 'responsable_it', 'parent']}>
              <PlanRoute feature="calendrier_scolaire">
                <Calendrier />
              </PlanRoute>
            </RoleRoute>
          }
        />
        <Route
          path="archives"
          element={
            <RoleRoute roles={['admin']}>
              <PlanRoute feature="archivage">
                <Archives />
              </PlanRoute>
            </RoleRoute>
          }
        />
        <Route
          path="groupe"
          element={
            <RoleRoute roles={['admin']}>
              <PlanRoute feature="multi_sites">
                <Groupe />
              </PlanRoute>
            </RoleRoute>
          }
        />
        <Route
          path="actualites"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'censeur', 'cpe', 'enseignant', 'secretaire', 'gestionnaire', 'agent_comptable', 'responsable_it', 'parent']}>
              <PlanRoute feature="fil_actualite">
                <Actualites />
              </PlanRoute>
            </RoleRoute>
          }
        />
        <Route
          path="planning-examens"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'censeur', 'cpe', 'enseignant', 'secretaire']}>
              <PlanRoute feature="planning_examens">
                <PlanningExamens />
              </PlanRoute>
            </RoleRoute>
          }
        />
        <Route
          path="chatboard"
          element={
            <RoleRoute roles={['parent']}>
              <PlanRoute feature="espace_parent">
                <Chatboard />
              </PlanRoute>
            </RoleRoute>
          }
        />
        <Route
          path="cahier-texte"
          element={
            <RoleRoute roles={['enseignant', 'admin', 'proviseur', 'censeur', 'parent']}>
              <PlanRoute feature="cahier_texte">
                <CahierTexte />
              </PlanRoute>
            </RoleRoute>
          }
        />
        <Route
          path="donnees"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'secretaire']}>
              <Donnees />
            </RoleRoute>
          }
        />
        <Route
          path="justifications"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'censeur', 'cpe']}>
              <PlanRoute feature="espace_parent">
                <Justifications />
              </PlanRoute>
            </RoleRoute>
          }
        />
        <Route
          path="analyses"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'censeur']}>
              <PlanRoute feature="analyses_avancees">
                <Analyses />
              </PlanRoute>
            </RoleRoute>
          }
        />
        <Route
          path="personnalisation"
          element={
            <RoleRoute roles={['admin']}>
              <PlanRoute feature="personnalisation">
                <Personnalisation />
              </PlanRoute>
            </RoleRoute>
          }
        />
        <Route
          path="acces-api"
          element={
            <RoleRoute roles={['admin']}>
              <PlanRoute feature="api">
                <AccesApi />
              </PlanRoute>
            </RoleRoute>
          }
        />
        <Route
          path="support"
          element={
            <RoleRoute roles={['admin', 'proviseur']}>
              <PlanRoute feature="support_prioritaire">
                <Support />
              </PlanRoute>
            </RoleRoute>
          }
        />
        <Route
          path="audits"
          element={
            <RoleRoute roles={['admin', 'responsable_it', 'super_admin']}>
              <PlanRoute feature="journal_export">
                <Audits />
              </PlanRoute>
            </RoleRoute>
          }
        />
        <Route
          path="super-admin/:section?"
          element={
            <RoleRoute roles={['super_admin']}>
              <SuperAdminRoute />
            </RoleRoute>
          }
        />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
