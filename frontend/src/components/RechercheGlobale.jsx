import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import client from '../api/client'

const SECTIONS = [
  { cle: 'eleves', titre: 'Élèves' },
  { cle: 'classes', titre: 'Classes' },
  { cle: 'enseignants', titre: 'Enseignants' },
]

// Recherche globale : élèves, classes et enseignants, depuis n'importe quel écran.
export default function RechercheGlobale() {
  const navigate = useNavigate()
  const [terme, setTerme] = useState('')
  const [resultats, setResultats] = useState(null)
  const [ouvert, setOuvert] = useState(false)
  const [actif, setActif] = useState(-1)
  const conteneur = useRef(null)

  // Interroge l'API après une courte pause de saisie.
  useEffect(() => {
    if (terme.trim().length < 2) {
      setResultats(null)
      return undefined
    }
    const minuteur = setTimeout(() => {
      client
        .get('/recherche', { params: { q: terme.trim() } })
        .then((res) => setResultats(res.data))
        .catch(() => setResultats(null))
    }, 250)
    return () => clearTimeout(minuteur)
  }, [terme])

  // Ferme la liste quand on clique ailleurs.
  useEffect(() => {
    const fermer = (e) => {
      if (conteneur.current && !conteneur.current.contains(e.target)) setOuvert(false)
    }
    document.addEventListener('mousedown', fermer)
    return () => document.removeEventListener('mousedown', fermer)
  }, [])

  const plats = SECTIONS.flatMap((s) => (resultats?.[s.cle] || []).map((r) => ({ ...r, section: s.titre })))

  const ouvrir = (r) => {
    setOuvert(false)
    setTerme('')
    navigate(r.lien)
  }

  const surTouche = (e) => {
    if (e.key === 'Escape') {
      setOuvert(false)
    } else if (e.key === 'ArrowDown') {
      e.preventDefault()
      setActif((i) => Math.min(i + 1, plats.length - 1))
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      setActif((i) => Math.max(i - 1, 0))
    } else if (e.key === 'Enter' && actif >= 0 && plats[actif]) {
      ouvrir(plats[actif])
    }
  }

  const afficherListe = ouvert && terme.trim().length >= 2

  return (
    <div className="recherche-globale" ref={conteneur}>
      <input
        type="search"
        className="recherche-globale-champ"
        placeholder="Rechercher un élève, une classe, un enseignant…"
        aria-label="Recherche globale"
        aria-expanded={afficherListe}
        aria-autocomplete="list"
        value={terme}
        onChange={(e) => { setTerme(e.target.value); setOuvert(true); setActif(-1) }}
        onFocus={() => setOuvert(true)}
        onKeyDown={surTouche}
      />

      {afficherListe && (
        <div className="recherche-globale-liste" role="listbox">
          {resultats === null && <p className="recherche-globale-vide">Recherche en cours…</p>}
          {resultats !== null && plats.length === 0 && (
            <p className="recherche-globale-vide">Aucun résultat pour « {terme} ».</p>
          )}
          {SECTIONS.map((s) => {
            const items = plats.filter((p) => p.section === s.titre)
            if (items.length === 0) return null
            return (
              <div key={s.cle} className="recherche-globale-groupe">
                <span className="recherche-globale-titre">{s.titre}</span>
                {items.map((r) => {
                  const index = plats.indexOf(r)
                  return (
                    <button
                      key={`${s.cle}-${r.id}`}
                      type="button"
                      role="option"
                      aria-selected={index === actif}
                      className={`recherche-globale-item ${index === actif ? 'recherche-globale-item--actif' : ''}`}
                      onMouseEnter={() => setActif(index)}
                      onClick={() => ouvrir(r)}
                    >
                      <strong>{r.libelle}</strong>
                      {r.detail && <span>{r.detail}</span>}
                    </button>
                  )
                })}
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}
