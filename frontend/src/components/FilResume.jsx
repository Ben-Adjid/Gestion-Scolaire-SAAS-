import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'
import { useEtablissement } from '../context/EtablissementContext'
import { aLaFonctionnalite } from '../utils/plans'
import { PremiumBadge } from './PlanGate'

const date = (valeur) => new Date(valeur).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })

// Derniers messages du fil d'actualité, affichés sur le tableau de bord et dans l'espace parent.
export default function FilResume({ limite = 3, titre = 'Fil d’actualité' }) {
  const { user } = useAuth()
  const { etablissement } = useEtablissement()
  const [items, setItems] = useState(null)
  const ouvert = aLaFonctionnalite(user, etablissement, 'fil_actualite')

  useEffect(() => {
    if (ouvert) client.get('/actualites').then((res) => setItems(res.data.slice(0, limite))).catch(() => setItems([]))
  }, [ouvert, limite])

  if (!ouvert) {
    return (
      <section className="card resume-verrouille" style={{ marginBottom: 16 }}>
        <div className="card-body">
          <strong>{titre}</strong> <PremiumBadge feature="fil_actualite" />
          <p className="muted">Les annonces et événements de l’établissement, au même endroit.</p>
        </div>
      </section>
    )
  }

  if (items === null) return null

  return (
    <section className="card" style={{ marginBottom: 16 }}>
      <div className="card-header resume-entete">
        <h2>{titre}</h2>
        <Link to="/actualites" className="btn btn--ghost btn--sm">Tout voir</Link>
      </div>
      <div className="card-body">
        {items.length === 0 && <p className="muted">Aucune publication pour le moment.</p>}
        {items.map((a) => (
          <article key={a.id} className="actualite-mini">
            <strong>{a.titre}</strong>
            <span className="muted">{date(a.created_at)} · {a.auteur?.name}</span>
          </article>
        ))}
      </div>
    </section>
  )
}
