import { useEffect, useState } from 'react'
import { etablissementApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Alert from '../components/Alert'
import { Field, Input } from '../components/Field'
import { useToast } from '../components/Toast'
import { useEtablissement } from '../context/EtablissementContext'
import { paysCourant } from '../utils/pays'

const COULEUR_PAR_DEFAUT = '#1F5C4A'

// Palettes prêtes à l'emploi, toutes lisibles avec un texte blanc (contraste suffisant sur les boutons).
const PALETTES = [
  { nom: 'Forêt', couleur: '#1F5C4A' },
  { nom: 'Sahel', couleur: '#9A4B12' },
  { nom: 'Terre cuite', couleur: '#B23A2B' },
  { nom: 'Océan', couleur: '#0F5E8C' },
  { nom: 'Nuit', couleur: '#26306B' },
  { nom: 'Savane', couleur: '#6B5A12' },
  { nom: 'Prune', couleur: '#6A2C5E' },
  { nom: 'Ardoise', couleur: '#334155' },
]

// Formule Entreprise : couleur d'accent de l'espace de l'établissement.
export default function Personnalisation() {
  const toast = useToast()
  const { etablissement, setEtablissement } = useEtablissement()
  const [couleur, setCouleur] = useState(COULEUR_PAR_DEFAUT)
  const [enregistrement, setEnregistrement] = useState(false)
  const [erreur, setErreur] = useState(null)

  useEffect(() => {
    setCouleur(etablissement.couleur_principale || COULEUR_PAR_DEFAUT)
  }, [etablissement.couleur_principale])

  const enregistrer = async (e) => {
    e.preventDefault()
    setEnregistrement(true)
    setErreur(null)
    try {
      await etablissementApi.update({
        nom: etablissement.nom,
        annee_scolaire: etablissement.annee_scolaire,
        couleur_principale: couleur,
      })
      setEtablissement({ ...etablissement, couleur_principale: couleur })
      toast.success('Couleurs de l’espace enregistrées.')
    } catch (err) {
      setErreur(err.message)
    } finally {
      setEnregistrement(false)
    }
  }

  return (
    <div>
      <PageHeader title="Personnalisation" subtitle="Adaptez la couleur de votre espace à l’identité de votre établissement." />

      {erreur && <Alert>{erreur}</Alert>}

      <div className="personnalisation-grille">
        <form className="card form-carte" onSubmit={enregistrer}>
          <div className="card-body">
            <section className="form-section">
              <h3>Thème</h3>
              <p className="form-section-aide">Choisissez une palette, ou les couleurs de votre pays. Vous pouvez ensuite ajuster la teinte.</p>
              <div className="palettes">
                {[...(etablissement.pays_info?.couleur ? [{ nom: `Couleurs nationales (${paysCourant().nom})`, couleur: etablissement.pays_info.couleur }] : []), ...PALETTES].map((p) => (
                  <button
                    key={p.nom}
                    type="button"
                    className={`palette ${couleur.toLowerCase() === p.couleur.toLowerCase() ? 'palette--active' : ''}`}
                    onClick={() => setCouleur(p.couleur)}
                  >
                    <span className="palette-pastille" style={{ background: p.couleur }} />
                    {p.nom}
                  </button>
                ))}
              </div>
            </section>

            <section className="form-section">
              <h3>Couleur principale</h3>
              <p className="form-section-aide">Utilisée pour les boutons, la barre latérale et les accents.</p>
              <div className="couleur-champ">
                <input
                  type="color"
                  aria-label="Choisir la couleur"
                  value={couleur}
                  onChange={(e) => setCouleur(e.target.value)}
                  className="couleur-picker"
                />
                <Field label="Code couleur" htmlFor="couleur-hex">
                  <Input
                    id="couleur-hex"
                    value={couleur}
                    maxLength={7}
                    onChange={(e) => setCouleur(e.target.value)}
                    pattern="#[0-9A-Fa-f]{6}"
                  />
                </Field>
              </div>
            </section>

            <div className="form-actions">
              <button type="button" className="btn btn--ghost" onClick={() => setCouleur(COULEUR_PAR_DEFAUT)}>
                Rétablir la couleur par défaut
              </button>
              <button type="submit" className="btn btn--primary" disabled={enregistrement}>
                {enregistrement ? 'Enregistrement…' : 'Enregistrer'}
              </button>
            </div>
          </div>
        </form>

        <aside className="card apercu-carte" style={{ '--apercu': couleur }}>
          <div className="apercu-entete">
            <strong>{etablissement.nom}</strong>
          </div>
          <div className="apercu-menu">
            <span className="apercu-item apercu-item--actif">Tableau de bord</span>
            <span className="apercu-item">Élèves</span>
            <span className="apercu-item">Notes</span>
          </div>
          <button type="button" className="apercu-bouton">Bouton principal</button>
        </aside>
      </div>
    </div>
  )
}
