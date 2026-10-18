import { useCallback, useEffect, useState } from 'react'
import { absencesEnseignantsApi, pointagesApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Alert from '../components/Alert'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import { useToast } from '../components/Toast'
import { useAuth } from '../context/AuthContext'
import { Field, Input, Select } from '../components/Field'

export default function Pointer() {
  const { user } = useAuth()
  const toast = useToast()
  const [cours, setCours] = useState([])
  const [code, setCode] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [pointingId, setPointingId] = useState(null)

  const [signalType, setSignalType] = useState('absence')
  const [signalMotif, setSignalMotif] = useState('')
  const [signalLoading, setSignalLoading] = useState(false)

  const getPosition = () =>
    new Promise((resolve) => {
      if (!navigator.geolocation) {
        resolve(null)
        return
      }
      navigator.geolocation.getCurrentPosition(
        (pos) => resolve({ latitude: pos.coords.latitude, longitude: pos.coords.longitude }),
        () => resolve(null),
        { enableHighAccuracy: true, timeout: 8000 }
      )
    })

  const fetchCours = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await pointagesApi.aujourdhui()
      setCours(res.data.cours)
      setCode(res.data.code)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    fetchCours()
  }, [fetchCours])

  const pointer = async (cours) => {
    setPointingId(cours.emploi_du_temps_id)
    try {
      const pos = await getPosition()
      const res = await pointagesApi.store({
        emploi_du_temps_id: cours.emploi_du_temps_id,
        ...(pos || {}),
      })
      toast.success(res.data.message)
      fetchCours()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setPointingId(null)
    }
  }

  const signaler = async (e) => {
    e.preventDefault()
    setSignalLoading(true)
    try {
      const res = await absencesEnseignantsApi.create({
        type: signalType,
        motif: signalMotif || null,
        date: new Date().toISOString().slice(0, 10),
      })
      toast.success(res.data.message)
      setSignalMotif('')
    } catch (err) {
      toast.error(err.message)
    } finally {
      setSignalLoading(false)
    }
  }

  const now = new Date()

  return (
    <div>
      <PageHeader
        title="Pointer"
        subtitle={`Vos cours du jour — ${now.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })}`}
      />

      {error && <Alert>{error}</Alert>}

      {code && (
        <div className="info-panel" style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <span>🔑 Votre code de pointage :</span>
          <span className="badge badge--neutral" style={{ fontSize: 16, fontWeight: 700 }}>
            {code}
          </span>
        </div>
      )}

      <div className="card">
        <div className="card-body">
          {loading ? (
            <div className="page-loader"><Spinner /></div>
          ) : cours.length === 0 ? (
            <EmptyState
              title="Aucun cours aujourd'hui"
              description="Vous n'avez aucun cours programmé aujourd'hui."
            />
          ) : (
            <div className="pointer-list">
              {cours.map((c) => (
                <div key={c.emploi_du_temps_id} className={`pointer-course ${c.pointage ? 'pointer-course--done' : ''}`}>
                  <div className="pointer-course-info">
                    <strong>{c.matiere}</strong>
                    <span className="muted">
                      {c.classe} — {c.heure_debut} à {c.heure_fin}
                    </span>
                  </div>
                  <div className="pointer-course-action">
                    {c.pointage ? (
                      <span className={`badge ${c.pointage.statut === 'retard' ? 'badge--amber' : 'badge--success'}`}>
                        {c.pointage.statut === 'retard' ? 'Pointé en retard' : 'Pointé à l\'heure'} • {c.pointage.heure}
                      </span>
                    ) : c.pointable ? (
                      <button
                        type="button"
                        className="btn btn--primary"
                        onClick={() => pointer(c)}
                        disabled={pointingId === c.emploi_du_temps_id}
                      >
                        {pointingId === c.emploi_du_temps_id ? 'Pointage…' : '📍 Pointer'}
                      </button>
                    ) : (
                      <span className="muted">Fenêtre : {c.fenetre}</span>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      <div className="card" style={{ maxWidth: 560, marginTop: 20 }}>
        <div className="card-header">
          <h2>Je ne peux pas venir</h2>
        </div>
        <div className="card-body">
          <p className="field-hint" style={{ marginBottom: 14 }}>
            Signalez votre absence ou votre retard à l'administration.
          </p>
          <form onSubmit={signaler} noValidate>
            <Field label="Type" htmlFor="signal-type" required>
              <Select id="signal-type" value={signalType} onChange={(e) => setSignalType(e.target.value)}>
                <option value="absence">Absence</option>
                <option value="retard">Retard</option>
              </Select>
            </Field>
            <Field label="Motif" htmlFor="signal-motif">
              <Input
                id="signal-motif"
                value={signalMotif}
                onChange={(e) => setSignalMotif(e.target.value)}
                placeholder="Ex : maladie, problème familial…"
              />
            </Field>
            <button type="submit" className="btn btn--ghost" disabled={signalLoading}>
              {signalLoading ? 'Envoi…' : 'Signaler'}
            </button>
          </form>
        </div>
      </div>
    </div>
  )
}
