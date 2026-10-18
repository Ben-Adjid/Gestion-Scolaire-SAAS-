import { Navigate, Route, Routes } from 'react-router-dom'
import { useAuth } from './context/AuthContext'
import AppLayout from './components/AppLayout'
import Login from './pages/Login'
import Dashboard from './pages/Dashboard'
import Classes from './pages/Classes'
import Enseignants from './pages/Enseignants'
import Etudiants from './pages/Etudiants'
import Matieres from './pages/Matieres'
import Notes from './pages/Notes'
import Inscriptions from './pages/Inscriptions'
import Bulletins from './pages/Bulletins'
import Palmares from './pages/Palmares'
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

function ProtectedRoute({ children }) {
  const { user, loading } = useAuth()

  if (loading) {
    return (
      <div className="page-loader">
        <Spinner />
      </div>
    )
  }

  if (!user) {
    return <Navigate to="/login" replace />
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

function HomeRedirect() {
  const { user } = useAuth()

  return user?.role === 'super_admin'
    ? <Navigate to="/super-admin" replace />
    : <Dashboard />
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
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
            <RoleRoute roles={['admin', 'super_admin']}>
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
              <ClassesExamen />
            </RoleRoute>
          }
        />
        <Route
          path="dossiers-examen"
          element={
            <RoleRoute roles={['admin', 'proviseur', 'censeur', 'cpe', 'secretaire']}>
              <DossiersExamen />
            </RoleRoute>
          }
        />
        <Route
          path="audits"
          element={
            <RoleRoute roles={['admin', 'responsable_it', 'super_admin']}>
              <Audits />
            </RoleRoute>
          }
        />
        <Route
          path="super-admin"
          element={
            <RoleRoute roles={['super_admin']}>
              <SuperAdmin />
            </RoleRoute>
          }
        />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
