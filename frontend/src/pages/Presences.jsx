import { useState } from 'react'
import PageHeader from '../components/PageHeader'
import { useAuth } from '../context/AuthContext'
import Appels from './Appels'
import Absences from './Absences'
import Convocations from './Convocations'
import AppelsAConfirmer from './AppelsAConfirmer'

export default function Presences() {
  const { user } = useAuth()
  const isTeacher = user?.role === 'enseignant'
  const [tab, setTab] = useState('appel')

  return (
    <div>
      <PageHeader
        title="Présences & Absences"
        subtitle={
          isTeacher
            ? "Faites l'appel du jour pour vos classes."
            : "Appel du jour et historique des absences des élèves."
        }
      />

      {!isTeacher && (
        <div className="tabs">
          <button
            type="button"
            className={`tab ${tab === 'appel' ? 'tab--active' : ''}`}
            onClick={() => setTab('appel')}
          >
            Appel du jour
          </button>
          <button
            type="button"
            className={`tab ${tab === 'a-confirmer' ? 'tab--active' : ''}`}
            onClick={() => setTab('a-confirmer')}
          >
            À confirmer
          </button>
          <button
            type="button"
            className={`tab ${tab === 'historique' ? 'tab--active' : ''}`}
            onClick={() => setTab('historique')}
          >
            Historique
          </button>
          <button
            type="button"
            className={`tab ${tab === 'convocations' ? 'tab--active' : ''}`}
            onClick={() => setTab('convocations')}
          >
            Convocations
          </button>
        </div>
      )}

      {isTeacher || tab === 'appel' ? (
        <Appels />
      ) : tab === 'a-confirmer' ? (
        <AppelsAConfirmer />
      ) : tab === 'historique' ? (
        <Absences />
      ) : (
        <Convocations />
      )}
    </div>
  )
}
