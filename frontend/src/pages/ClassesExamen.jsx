import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { examensApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import Alert from '../components/Alert'
import EmptyState from '../components/EmptyState'
import { mentionColor } from '../utils/format'

function decisionBadge(decision) {
  switch (decision?.statut) {
    case 'admis':
      return 'badge--success'
    case 'redouble':
      return 'badge--danger'
    default:
      return 'badge--neutral'
  }
}

export default function ClassesExamen() {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    examensApi
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

  const { classes_examen, statistiques, eleves } = data

  const grouped = {}
  eleves.forEach((e) => {
    const key = e.classe?.nom || 'Sans classe'
    if (!grouped[key]) grouped[key] = []
    grouped[key].push(e)
  })

  return (
    <div>
      <PageHeader
        title="Classes d'examen"
        subtitle="Suivi des élèves de 3ème (Brevet) et de Terminale (Bac)."
      />

      <div className="stat-grid stat-grid--4">
        <div className="stat-card stat-card--indigo">
          <span className="stat-icon">🎓</span>
          <div className="stat-body">
            <span className="stat-value">{statistiques.total_eleves}</span>
            <span className="stat-label">Élèves en examen</span>
          </div>
        </div>
        <div className="stat-card stat-card--green">
          <span className="stat-icon">✅</span>
          <div className="stat-body">
            <span className="stat-value">{statistiques.admis}</span>
            <span className="stat-label">Admis</span>
          </div>
        </div>
        <div className="stat-card stat-card--rose">
          <span className="stat-icon">⚠️</span>
          <div className="stat-body">
            <span className="stat-value">{statistiques.non_admis}</span>
            <span className="stat-label">Non admis</span>
          </div>
        </div>
        <div className="stat-card stat-card--amber">
          <span className="stat-icon">📈</span>
          <div className="stat-body">
            <span className="stat-value">{statistiques.taux_admission}%</span>
            <span className="stat-label">Taux d'admission (≥ {statistiques.seuil})</span>
          </div>
        </div>
      </div>

      {classes_examen.length === 0 ? (
        <EmptyState
          title="Aucune classe d'examen"
          description="Aucune classe de 3ème ou de Terminale n'est enregistrée."
        />
      ) : (
        <div className="class-sections">
          {Object.entries(grouped).map(([classeNom, liste]) => (
            <section key={classeNom} className="class-section">
              <h3 className="class-section-title">
                {classeNom} <span className="class-section-count">({liste.length})</span>
              </h3>
              <div className="table-wrapper">
                <table className="table">
                  <thead>
                    <tr>
                      <th>Élève</th>
                      <th>Matricule</th>
                      <th>Moyenne annuelle</th>
                      <th>Mention</th>
                      <th>Décision</th>
                    </tr>
                  </thead>
                  <tbody>
                    {liste.map((e) => (
                      <tr key={e.id}>
                        <td className="cell-strong">{e.prenom} {e.nom}</td>
                        <td className="cell-muted">{e.matricule || '—'}</td>
                        <td className="cell-strong">{e.moyenne.toFixed(2)} /20</td>
                        <td>
                          {e.mention ? (
                            <span className={`mention-badge mention-badge--${mentionColor(e.mention.key)}`}>
                              {e.mention.label}
                            </span>
                          ) : '—'}
                        </td>
                        <td>
                          <span className={`badge ${decisionBadge(e.decision)}`}>
                            {e.decision.label}
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>
          ))}
        </div>
      )}
    </div>
  )
}
