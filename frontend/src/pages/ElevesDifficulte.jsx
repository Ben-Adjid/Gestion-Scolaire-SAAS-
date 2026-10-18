import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { suiviApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Alert from '../components/Alert'
import { Select } from '../components/Field'
import { noteClass } from '../utils/format'
import { TRIMESTRES_ANNUEL } from '../utils/constants'

export default function ElevesDifficulte() {
  const [trimestre, setTrimestre] = useState(0)
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const fetchData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await suiviApi.enDifficulte(trimestre === 0 ? { annuel: 1 } : { trimestre })
      setData(res.data)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [trimestre])

  useEffect(() => {
    fetchData()
  }, [fetchData])

  return (
    <div>
      <PageHeader
        title="Élèves en difficulté"
        subtitle="Élèves dont la moyenne est inférieure au seuil d'admission."
        extra={
          <Select value={trimestre} onChange={(e) => setTrimestre(Number(e.target.value))} className="filter-select" aria-label="Choisir le trimestre">
            {TRIMESTRES_ANNUEL.map((t) => (
              <option key={t.value} value={t.value}>{t.label}</option>
            ))}
          </Select>
        }
      />

      {error && <Alert>{error}</Alert>}

      {loading ? (
        <div className="page-loader"><Spinner /></div>
      ) : data && data.eleves.length === 0 ? (
        <EmptyState
          title="Aucun élève en difficulté"
          description={`Tous les élèves ont une moyenne supérieure ou égale à ${data.seuil}/20.`}
        />
      ) : data ? (
        <div className="card">
          <div className="card-header">
            <h2>{data.eleves.length} élève(s) en dessous de {data.seuil}/20</h2>
          </div>
          <div className="card-body">
            <div className="table-wrapper">
              <table className="table">
                <thead>
                  <tr>
                    <th>Rang</th>
                    <th>Élève</th>
                    <th>Classe</th>
                    <th>Moyenne</th>
                    <th className="th-actions">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {data.eleves.map((eleve, index) => (
                    <tr key={eleve.id}>
                      <td className="cell-muted">{index + 1}</td>
                      <td className="cell-strong">{eleve.prenom} {eleve.nom}</td>
                      <td><span className="badge">{eleve.classe?.nom}</span></td>
                      <td>
                        <span className={`note-badge ${noteClass(eleve.moyenne)}`}>
                          {(parseFloat(eleve.moyenne) || 0).toFixed(2)} /20
                        </span>
                      </td>
                      <td className="cell-actions">
                        <Link to={`/bulletins`} className="btn btn--ghost btn--sm">Bulletin</Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  )
}
