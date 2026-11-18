import { useMemo, useState } from 'react'

// Graphique interactif : moyenne de chaque classe pour le trimestre choisi.
// Survol ou focus clavier : détail de la classe ; trait pointillé à 10/20 (seuil de réussite).
export default function ChartMoyennesClasses({ donnees = [] }) {
  const [trimestre, setTrimestre] = useState(0) // 0 = tous les trimestres (moyenne annuelle des notes)
  const [survol, setSurvol] = useState(null)

  const barres = useMemo(() => {
    const filtrees = trimestre === 0
      ? Object.values(donnees.reduce((acc, l) => {
          const a = acc[l.classe] || { classe: l.classe, somme: 0, notes: 0 }
          a.somme += Number(l.moyenne) * Number(l.nb_notes)
          a.notes += Number(l.nb_notes)
          acc[l.classe] = a
          return acc
        }, {})).map((a) => ({ classe: a.classe, moyenne: a.notes ? Math.round((a.somme / a.notes) * 100) / 100 : 0, nb_notes: a.notes }))
      : donnees.filter((l) => Number(l.trimestre) === trimestre)
    return filtrees.map((l) => ({ classe: l.classe, moyenne: Number(l.moyenne), nb_notes: Number(l.nb_notes) }))
  }, [donnees, trimestre])

  const largeur = 640
  const hauteur = 260
  const marge = { haut: 20, bas: 56, gauche: 36, droite: 12 }
  const zoneL = largeur - marge.gauche - marge.droite
  const zoneH = hauteur - marge.haut - marge.bas
  const pas = barres.length ? zoneL / barres.length : zoneL
  const largeurBarre = Math.min(56, pas * 0.6)
  const yPour = (valeur) => marge.haut + zoneH - (Math.max(0, Math.min(20, valeur)) / 20) * zoneH
  const couleur = (v) => (v >= 14 ? '#16a34a' : v >= 10 ? '#1F5C4A' : '#dc2626')

  const boutons = [
    { valeur: 0, libelle: 'Année' },
    { valeur: 1, libelle: '1er trim.' },
    { valeur: 2, libelle: '2e trim.' },
    { valeur: 3, libelle: '3e trim.' },
  ]

  return (
    <div className="chart-moyennes">
      <div className="chart-moyennes-entete">
        <div className="chart-moyennes-filtres" role="group" aria-label="Choisir le trimestre">
          {boutons.map((b) => (
            <button
              key={b.valeur}
              type="button"
              className={`btn btn--sm ${trimestre === b.valeur ? 'btn--primary' : 'btn--ghost'}`}
              aria-pressed={trimestre === b.valeur}
              onClick={() => setTrimestre(b.valeur)}
            >
              {b.libelle}
            </button>
          ))}
        </div>
      </div>

      {barres.length === 0 ? (
        <p className="muted">Aucune note pour ce trimestre.</p>
      ) : (
        <div className="chart-moyennes-zone">
          <svg viewBox={`0 0 ${largeur} ${hauteur}`} role="img" aria-label="Moyennes par classe" className="chart-moyennes-svg">
            {[0, 5, 10, 15, 20].map((g) => (
              <g key={g}>
                <line x1={marge.gauche} x2={largeur - marge.droite} y1={yPour(g)} y2={yPour(g)} stroke="#E4DED3" strokeWidth="1" />
                <text x={marge.gauche - 8} y={yPour(g) + 4} textAnchor="end" fontSize="11" fill="#78716A">{g}</text>
              </g>
            ))}
            <line
              x1={marge.gauche} x2={largeur - marge.droite} y1={yPour(10)} y2={yPour(10)}
              stroke="#C2703D" strokeWidth="1.5" strokeDasharray="5 4"
            />
            {barres.map((b, i) => {
              const x = marge.gauche + i * pas + (pas - largeurBarre) / 2
              const y = yPour(b.moyenne)
              const actif = survol === i
              return (
                <g
                  key={b.classe}
                  tabIndex={0}
                  onMouseEnter={() => setSurvol(i)}
                  onMouseLeave={() => setSurvol(null)}
                  onFocus={() => setSurvol(i)}
                  onBlur={() => setSurvol(null)}
                  style={{ cursor: 'pointer', outline: 'none' }}
                  aria-label={`${b.classe} : moyenne ${b.moyenne} sur 20, ${b.nb_notes} notes`}
                >
                  <rect
                    x={x} y={y} width={largeurBarre} height={marge.haut + zoneH - y} rx="6"
                    fill={couleur(b.moyenne)} opacity={survol === null || actif ? 1 : 0.45}
                    style={{ transition: 'all 250ms ease' }}
                  />
                  <text x={x + largeurBarre / 2} y={y - 6} textAnchor="middle" fontSize="12" fontWeight="700" fill="#15302A">
                    {b.moyenne.toFixed(1)}
                  </text>
                  <text x={x + largeurBarre / 2} y={hauteur - 30} textAnchor="middle" fontSize="11" fill="#15302A">
                    {b.classe.length > 12 ? `${b.classe.slice(0, 11)}…` : b.classe}
                  </text>
                </g>
              )
            })}
          </svg>

          {survol !== null && barres[survol] && (
            <div className="chart-moyennes-infobulle" role="status">
              <strong>{barres[survol].classe}</strong>
              <span>Moyenne : {barres[survol].moyenne.toFixed(2)} / 20</span>
              <span>{barres[survol].nb_notes} note(s) saisie(s)</span>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
