import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { etudiantsApi } from '../api/endpoints'
import Alert from '../components/Alert'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import { formatDate } from '../utils/format'

export default function EtudiantSuivi() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [data, setData] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    let active = true
    etudiantsApi.suivi(id)
      .then((response) => {
        if (active) setData(response.data)
      })
      .catch((err) => {
        if (active) setError(err.response?.data?.message || 'Impossible de charger la fiche de suivi.')
      })
    return () => { active = false }
  }, [id])

  if (error) return <Alert>{error}</Alert>
  if (!data) return <div className="page-loader"><Spinner /></div>

  const { eleve, classe, synthese } = data
  return (
    <div>
      <PageHeader
        title={`Suivi de ${eleve.prenom} ${eleve.nom}`}
        subtitle={`${eleve.matricule || 'Sans matricule'} · ${classe?.nom || 'Sans classe'}`}
        extra={<button type="button" className="btn btn--ghost" onClick={() => navigate('/etudiants')}>Retour aux élèves</button>}
      />

      <div className="stat-grid stat-grid--3">
        <div className="stat-card stat-card--indigo"><span>Notes</span><strong>{synthese.notes}</strong><small>Moyenne : {synthese.moyenne ?? '—'}</small></div>
        <div className="stat-card stat-card--rose"><span>Absences</span><strong>{synthese.absences}</strong></div>
        <div className="stat-card stat-card--amber"><span>Retards</span><strong>{synthese.retards}</strong></div>
      </div>

      <section className="card">
        <h2>Notes</h2>
        {data.notes.length ? (
          <div className="table-wrapper"><table className="table">
            <thead><tr><th>Matière</th><th>Trimestre</th><th>Type</th><th>Note</th><th>Appréciation</th><th>État</th></tr></thead>
            <tbody>{data.notes.map((note, index) => (
              <tr key={`${note.matiere}-${note.trimestre}-${index}`}>
                <td>{note.matiere || '—'}</td><td>{note.trimestre}</td><td>{note.type}</td><td>{note.valeur}</td>
                <td>{note.appreciation || '—'}</td><td>{note.statut}</td>
              </tr>
            ))}</tbody>
          </table></div>
        ) : <p>Aucune note enregistrée.</p>}
      </section>

      <section className="card">
        <h2>Absences et retards</h2>
        {data.absences.length ? (
          <div className="table-wrapper"><table className="table">
            <thead><tr><th>Date</th><th>Type</th><th>Justification</th><th>Motif</th></tr></thead>
            <tbody>{data.absences.map((absence, index) => (
              <tr key={`${absence.date}-${index}`}><td>{formatDate(absence.date)}</td><td>{absence.type}</td><td>{absence.justifie ? 'Justifiée' : 'Non justifiée'}</td><td>{absence.motif || '—'}</td></tr>
            ))}</tbody>
          </table></div>
        ) : <p>Aucune absence ou retard enregistré.</p>}
      </section>

    </div>
  )
}
