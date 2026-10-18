import { useState } from 'react'
import PageHeader from '../components/PageHeader'
import Pointages from './Pointages'
import AbsencesEnseignants from './AbsencesEnseignants'

export default function PresenceEnseignants() {
  const [tab, setTab] = useState('pointages')

  return (
    <div>
      <PageHeader
        title="Présence des enseignants"
        subtitle="Pointages d'arrivée et absences signalées."
      />

      <div className="tabs">
        <button
          type="button"
          className={`tab ${tab === 'pointages' ? 'tab--active' : ''}`}
          onClick={() => setTab('pointages')}
        >
          Pointages
        </button>
        <button
          type="button"
          className={`tab ${tab === 'absences' ? 'tab--active' : ''}`}
          onClick={() => setTab('absences')}
        >
          Absences signalées
        </button>
      </div>

      {tab === 'pointages' ? <Pointages /> : <AbsencesEnseignants />}
    </div>
  )
}
