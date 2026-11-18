import { useEffect, useState } from 'react'
import { apiTokensApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Alert from '../components/Alert'
import { Field, Input } from '../components/Field'
import { useToast } from '../components/Toast'

// Formule Entreprise : création et révocation des clés d'accès à l'API.
export default function AccesApi() {
  const toast = useToast()
  const [cles, setCles] = useState(null)
  const [nom, setNom] = useState('')
  const [nouvelleCle, setNouvelleCle] = useState(null)
  const [erreur, setErreur] = useState(null)
  const [envoi, setEnvoi] = useState(false)

  const charger = () => apiTokensApi.liste().then((res) => setCles(res.data)).catch((e) => setErreur(e.message))

  useEffect(() => {
    charger()
  }, [])

  const creer = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    try {
      const res = await apiTokensApi.creer(nom)
      setNouvelleCle(res.data.token)
      setNom('')
      charger()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setEnvoi(false)
    }
  }

  const revoquer = async (cle) => {
    if (!window.confirm(`Révoquer la clé « ${cle.name} » ? Les applications qui l'utilisent ne fonctionneront plus.`)) return
    try {
      await apiTokensApi.revoquer(cle.id)
      toast.success('Clé révoquée.')
      charger()
    } catch (err) {
      toast.error(err.message)
    }
  }

  if (cles === null && !erreur) return <Spinner />

  return (
    <div>
      <PageHeader title="Accès API" subtitle="Connectez vos outils à votre établissement avec une clé personnelle." />

      {erreur && <Alert>{erreur}</Alert>}

      {nouvelleCle && (
        <div className="card cle-nouvelle">
          <div className="card-body">
            <strong>Votre nouvelle clé</strong>
            <p className="form-section-aide">Copiez-la maintenant : elle ne sera plus jamais affichée.</p>
            <code className="cle-valeur">{nouvelleCle}</code>
            <div className="form-actions">
              <button type="button" className="btn btn--ghost" onClick={() => navigator.clipboard?.writeText(nouvelleCle)}>Copier</button>
              <button type="button" className="btn btn--primary" onClick={() => setNouvelleCle(null)}>J’ai copié la clé</button>
            </div>
          </div>
        </div>
      )}

      <form className="card form-carte" onSubmit={creer}>
        <div className="card-body">
          <section className="form-section">
            <h3>Nouvelle clé</h3>
            <p className="form-section-aide">Donnez un nom qui indique à quoi sert la clé, par exemple « Tableau de bord Excel ».</p>
            <Field label="Nom de la clé" htmlFor="cle-nom" required>
              <Input id="cle-nom" value={nom} maxLength={100} onChange={(e) => setNom(e.target.value)} />
            </Field>
          </section>
          <div className="form-actions">
            <button type="submit" className="btn btn--primary" disabled={envoi || !nom.trim()}>
              {envoi ? 'Création…' : 'Créer la clé'}
            </button>
          </div>
        </div>
      </form>

      <section className="card" style={{ marginTop: 16 }}>
        <div className="card-header"><h2>Clés actives</h2></div>
        <div className="card-body">
          {cles?.length === 0 && <EmptyState title="Aucune clé" description="Créez une clé pour commencer à utiliser l’API." />}
          {cles?.length > 0 && (
            <ul className="top-list">
              {cles.map((cle) => (
                <li key={cle.id} className="top-item">
                  <span className="top-name">{cle.name}</span>
                  <span className="top-classe">
                    Créée le {new Date(cle.created_at).toLocaleDateString('fr-FR')}
                    {cle.last_used_at ? ` · dernier usage ${new Date(cle.last_used_at).toLocaleDateString('fr-FR')}` : ' · jamais utilisée'}
                  </span>
                  <button type="button" className="btn btn--ghost btn--sm" onClick={() => revoquer(cle)}>Révoquer</button>
                </li>
              ))}
            </ul>
          )}
        </div>
      </section>
    </div>
  )
}
