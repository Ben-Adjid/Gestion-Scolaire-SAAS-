import { useState } from 'react'
import client from '../api/client'
import { telecharger } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Alert from '../components/Alert'
import EmptyState from '../components/EmptyState'
import { useToast } from '../components/Toast'

// Exports en Excel (.xlsx) mis en forme, ou en CSV pour les outils qui le demandent.
const EXPORTS = [
  { chemin: '/export/etudiants', fichier: 'eleves', titre: 'Élèves', detail: 'Identité, classe, coordonnées.' },
  { chemin: '/export/notes', fichier: 'notes', titre: 'Notes', detail: 'Toutes les notes saisies, par élève et par matière.' },
]

// Import d'élèves depuis un fichier CSV (détection des colonnes, aperçu, puis import) et exports.
export default function Donnees() {
  const toast = useToast()
  const [fichier, setFichier] = useState(null)
  const [analyse, setAnalyse] = useState(null)
  const [traitement, setTraitement] = useState(false)
  const [erreur, setErreur] = useState(null)

  // Correspondance champ → colonne du fichier, modifiable à l'écran (chaîne vide = champ ignoré).
  const [correspondance, setCorrespondance] = useState({})

  const envoyer = async (mode, correspondanceChoisie = correspondance) => {
    const formulaire = new FormData()
    formulaire.append('fichier', fichier)
    formulaire.append('mode', mode)
    const choisie = Object.fromEntries(Object.entries(correspondanceChoisie).map(([k, v]) => [k, v === '' ? null : Number(v)]))
    if (Object.keys(choisie).length > 0) formulaire.append('mapping', JSON.stringify(choisie))
    setTraitement(true)
    setErreur(null)
    try {
      const res = await client.post('/import/etudiants', formulaire, { headers: { 'Content-Type': 'multipart/form-data' } })
      if (mode === 'apercu') {
        setAnalyse(res.data)
        if (Object.keys(correspondance).length === 0) {
          setCorrespondance(Object.fromEntries(res.data.champs.map((c) => [c.champ, c.colonne === null ? '' : String(c.colonne)])))
        }
      } else {
        toast.success(res.data.message)
        setAnalyse(null)
        setFichier(null)
      }
    } catch (err) {
      setErreur(err.message)
    } finally {
      setTraitement(false)
    }
  }

  const exporter = async (export_) => {
    try {
      const res = await client.get(export_.chemin, { responseType: 'blob' })
      telecharger(res.data, export_.fichier)
    } catch (err) {
      toast.error(err.message)
    }
  }

  return (
    <div>
      <PageHeader title="Import & export" subtitle="Reprenez vos données existantes, ou téléchargez-les à tout moment." />

      {erreur && <Alert>{erreur}</Alert>}

      <section className="card donnees-carte">
        <div className="card-header"><h2>Importer des élèves</h2></div>
        <div className="card-body">
          <p className="form-section-aide">
            Fichier Excel (.xlsx) ou CSV. Les colonnes sont reconnues automatiquement, même avec une faute de frappe.
            Vérifiez la correspondance avant d’importer. Les doublons sont ignorés.
          </p>

          <label className="fichier-depot">
            <input
              type="file"
              accept=".xlsx,.csv,text/csv"
              onChange={(e) => { setFichier(e.target.files[0] || null); setAnalyse(null); setCorrespondance({}) }}
            />
            <span>{fichier ? fichier.name : 'Choisir un fichier Excel ou CSV'}</span>
          </label>

          <div className="form-actions">
            <button type="button" className="btn btn--ghost" disabled={!fichier || traitement} onClick={() => envoyer('apercu')}>
              {traitement ? 'Analyse…' : 'Analyser le fichier'}
            </button>
          </div>

          {analyse && (
            <div className="import-apercu">
              <div className="import-kpis">
                <div><strong>{analyse.total}</strong><span>lignes</span></div>
                <div className="import-kpi--ok"><strong>{analyse.valides}</strong><span>valides</span></div>
                <div className="import-kpi--alerte"><strong>{analyse.erreurs.length}</strong><span>erreurs</span></div>
              </div>

              <h3>Correspondance des colonnes</h3>
              <p className="form-section-aide">Pour chaque champ, choisissez la colonne du fichier. « Ignorer » exclut le champ.</p>
              <div className="mappage">
                {analyse.champs.map((c) => (
                  <div key={c.champ} className="mappage-ligne">
                    <label htmlFor={`map-${c.champ}`} className="mappage-champ">
                      {c.libelle}{c.obligatoire && <span className="champ-requis"> *</span>}
                    </label>
                    <select
                      id={`map-${c.champ}`}
                      className="input"
                      value={correspondance[c.champ] ?? ''}
                      onChange={(e) => setCorrespondance((prev) => ({ ...prev, [c.champ]: e.target.value }))}
                    >
                      <option value="">Ignorer</option>
                      {analyse.entetes.map((entete, index) => (
                        <option key={index} value={String(index)}>{entete || `Colonne ${index + 1}`}</option>
                      ))}
                    </select>
                    <span className="mappage-exemple">{c.exemple ? `ex. : ${c.exemple}` : '—'}</span>
                  </div>
                ))}
              </div>
              <div className="form-actions">
                <button type="button" className="btn btn--ghost" disabled={traitement} onClick={() => envoyer('apercu')}>
                  Appliquer la correspondance
                </button>
              </div>

              {analyse.erreurs.length > 0 && (
                <ul className="import-erreurs">
                  {analyse.erreurs.slice(0, 8).map((e) => (
                    <li key={e.ligne}>Ligne {e.ligne} : {e.message}</li>
                  ))}
                </ul>
              )}

              {analyse.apercu.length === 0 ? (
                <EmptyState title="Aucune ligne valide" description="Corrigez le fichier puis relancez l’analyse." />
              ) : (
                <div className="table-wrapper">
                  <table className="table">
                    <thead><tr><th>Nom</th><th>Prénom</th><th>Classe</th><th>Naissance</th><th>Genre</th></tr></thead>
                    <tbody>
                      {analyse.apercu.map((l, i) => (
                        <tr key={i}>
                          <td>{l.nom}</td><td>{l.prenom}</td><td>{l.classe || '—'}</td><td>{l.date_naissance || '—'}</td><td>{l.genre || '—'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}

              <div className="form-actions">
                <button type="button" className="btn btn--primary" disabled={traitement || analyse.valides === 0} onClick={() => envoyer('importer')}>
                  Importer {analyse.valides} élève{analyse.valides > 1 ? 's' : ''}
                </button>
              </div>
            </div>
          )}
        </div>
      </section>

      <section className="card donnees-carte" style={{ marginTop: 16 }}>
        <div className="card-header"><h2>Exporter</h2></div>
        <div className="card-body exports-grille">
          {EXPORTS.map((e) => (
            <div key={e.chemin} className="export-carte">
              <strong>{e.titre}</strong>
              <span>{e.detail}</span>
              <div style={{ display: 'flex', gap: 8, marginTop: 10 }}>
                <button type="button" className="btn btn--primary btn--sm" onClick={() => exporter({ ...e, fichier: `${e.fichier}.xlsx`, chemin: `${e.chemin}?format=xlsx` })}>Excel (.xlsx)</button>
                <button type="button" className="btn btn--ghost btn--sm" onClick={() => exporter({ ...e, fichier: `${e.fichier}.csv`, chemin: `${e.chemin}?format=csv` })}>CSV</button>
              </div>
            </div>
          ))}
        </div>
      </section>
    </div>
  )
}
