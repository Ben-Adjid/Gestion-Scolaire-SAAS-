import { useEffect, useState } from 'react'
import client from '../api/client'
import { telecharger } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Alert from '../components/Alert'
import { Field, Input } from '../components/Field'
import { useToast } from '../components/Toast'

// Archives annuelles scellées : chaque année figée, avec une empreinte qui détecte toute altération.
export default function Archives() {
  const toast = useToast()
  const [archives, setArchives] = useState(null)
  const [annee, setAnnee] = useState('')
  const [envoi, setEnvoi] = useState(false)
  const [erreur, setErreur] = useState(null)
  const [verifications, setVerifications] = useState({})

  const charger = () => client.get('/archives').then((res) => setArchives(res.data)).catch((e) => setErreur(e.message))

  useEffect(() => {
    charger()
  }, [])

  const sceller = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    try {
      await client.post('/archives', { annee_scolaire: annee })
      toast.success('Année archivée et scellée.')
      setAnnee('')
      charger()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setEnvoi(false)
    }
  }

  const verifier = async (archive) => {
    try {
      const res = await client.get(`/archives/${archive.id}/verifier`)
      setVerifications({ ...verifications, [archive.id]: res.data.intacte })
    } catch (err) {
      toast.error(err.message)
    }
  }

  const telechargerJson = async (archive) => {
    try {
      const res = await client.get(`/archives/${archive.id}`)
      const blob = new Blob([JSON.stringify(res.data, null, 2)], { type: 'application/json' })
      telecharger(blob, `archive-${archive.annee_scolaire}.json`)
    } catch (err) {
      toast.error(err.message)
    }
  }

  if (archives === null && !erreur) return <Spinner />

  return (
    <div>
      <PageHeader title="Archives annuelles" subtitle="Figez une année scolaire : les données ne peuvent plus être modifiées." />
      {erreur && <Alert>{erreur}</Alert>}

      <form className="card form-carte" onSubmit={sceller}>
        <div className="card-body">
          <section className="form-section">
            <h3>Archiver une année</h3>
            <p className="form-section-aide">
              L’archive contient les élèves, notes, absences, frais et paiements à la date de l’archivage.
              Une empreinte SHA-256 est calculée : toute modification ultérieure sera signalée.
            </p>
            <Field label="Année scolaire" htmlFor="arc-annee" required hint="Format : 2025-2026">
              <Input id="arc-annee" value={annee} placeholder="2025-2026" maxLength={9} onChange={(e) => setAnnee(e.target.value)} />
            </Field>
          </section>
          <div className="form-actions">
            <button type="submit" className="btn btn--primary" disabled={envoi || !/^\d{4}-\d{4}$/.test(annee)}>
              {envoi ? 'Scellement…' : 'Archiver et sceller'}
            </button>
          </div>
        </div>
      </form>

      {archives?.length === 0 && <EmptyState title="Aucune archive" description="Les années archivées apparaîtront ici." />}

      {archives?.map((a) => (
        <article key={a.id} className="card actualite-carte">
          <div className="card-body">
            <header className="actualite-entete">
              <strong>Année {a.annee_scolaire}</strong>
              <span className="muted">
                Archivée le {new Date(a.created_at).toLocaleDateString('fr-FR')} par {a.auteur?.name || '—'}
              </span>
              <code className="muted">Empreinte : {a.empreinte.slice(0, 16)}…</code>
            </header>
            {verifications[a.id] !== undefined && (
              <p>
                {verifications[a.id]
                  ? <span className="badge badge--success">Intacte : aucune modification détectée</span>
                  : <span className="badge badge--danger">Altérée : le contenu ne correspond plus à l’empreinte</span>}
              </p>
            )}
            <div className="form-actions">
              <button type="button" className="btn btn--ghost btn--sm" onClick={() => verifier(a)}>Vérifier l’intégrité</button>
              <button type="button" className="btn btn--ghost btn--sm" onClick={() => telechargerJson(a)}>Télécharger (JSON)</button>
            </div>
          </div>
        </article>
      ))}
    </div>
  )
}
