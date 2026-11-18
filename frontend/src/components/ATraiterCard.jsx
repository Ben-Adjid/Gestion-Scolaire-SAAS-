import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import client from '../api/client'

// Ce qui attend une décision de la direction aujourd'hui, avec un accès direct à chaque liste.
export default function ATraiterCard() {
  const [donnees, setDonnees] = useState(null)
  const [erreur, setErreur] = useState(false)

  useEffect(() => {
    client
      .get('/a-traiter')
      .then((res) => setDonnees(res.data))
      .catch(() => setErreur(true))
  }, [])

  if (erreur || !donnees) return null

  return (
    <section className="card" style={{ marginBottom: 16 }}>
      <div className="card-header">
        <h2>À traiter aujourd’hui</h2>
        <span className="muted">{donnees.total} action(s) en attente</span>
      </div>
      <div className="card-body">
        {donnees.taches.length === 0 ? (
          <p className="muted">Rien à traiter. Tout est à jour.</p>
        ) : (
          <ul className="dash-ranking">
            {donnees.taches.map((t) => (
              <li key={t.libelle} className="dash-ranking-row">
                <div className="dash-ranking-info">
                  <span className="top-name">{t.libelle}</span>
                </div>
                <strong className="top-moyenne">{t.nombre}</strong>
                <Link to={t.lien} className="btn btn--ghost btn--sm">Ouvrir</Link>
              </li>
            ))}
          </ul>
        )}
      </div>
    </section>
  )
}
