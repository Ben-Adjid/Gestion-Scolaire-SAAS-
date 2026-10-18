import { useEffect, useState } from 'react'
import { bulletinApi, classesApi, etudiantsApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import Alert from '../components/Alert'
import EmptyState from '../components/EmptyState'
import { Select } from '../components/Field'
import { useToast } from '../components/Toast'
import { useEtablissement } from '../context/EtablissementContext'
import { mentionColor } from '../utils/format'
import { TRIMESTRES_ANNUEL } from '../utils/constants'
import { downloadElementPdf, downloadBulletinsPdf } from '../utils/pdf'

const ETABLISSEMENT = {
  pays: 'RÉPUBLIQUE DU TCHAD',
  slogan: 'Unité — Travail — Progrès',
  ministere: 'Ministère de l’Éducation Nationale',
  nom: 'Lycée Ibnou Cina',
  adresse: 'BP 123, N’Djaména — Tél: +235 66 00 00 00',
  logoText: 'LYCÉE IBNOU CINA',
}
// Fonction pour mapper le niveau à Collège ou Lycée
const getNiveauLabel = (niveau) => {
  if (!niveau) return ''
  const cleanNiveau = niveau.toString().trim().toLowerCase()
  
  if (
    /^(6|5|4|3)/.test(cleanNiveau) || 
    cleanNiveau.includes('coll') || 
    cleanNiveau.includes('clg')
  ) {
    return 'Collège'
  }
  
  if (
    /^(2|1|t)/.test(cleanNiveau) ||
    cleanNiveau.includes('seconde') || 
    cleanNiveau.includes('première') || 
    cleanNiveau.includes('premiere') || 
    cleanNiveau.includes('terminale') ||
    cleanNiveau.includes('lyc')
  ) {
    return 'Lycée'
  }
  
  return niveau.charAt(0).toUpperCase() + niveau.slice(1)
}

// Fonction pour grouper les matières par domaine
const groupMatieresByDomaine = (matieres) => {
  const domaines = {
    'Scientifiques': [],
    'Littéraires': [],
    'Langues': [],
    'EPS': [],
    'Autres': []
  }

  const scientifiques = ['Mathématiques', 'Physique-Chimie', 'SVT']
  const litteraires = ['Français', 'Histoire-Géographie', 'Philosophie']
  const langues = ['Anglais', 'Espagnol', 'Allemand', 'Italien', 'Chinois', 'Arabe']
  const eps = ['EPS', 'Éducation Physique']

  matieres.forEach(matiere => {
    if (scientifiques.some(s => matiere.matiere.nom.includes(s))) {
      domaines['Scientifiques'].push(matiere)
    } else if (litteraires.some(l => matiere.matiere.nom.includes(l))) {
      domaines['Littéraires'].push(matiere)
    } else if (langues.some(l => matiere.matiere.nom.includes(l))) {
      domaines['Langues'].push(matiere)
    } else if (eps.some(e => matiere.matiere.nom.includes(e))) {
      domaines['EPS'].push(matiere)
    } else {
      domaines['Autres'].push(matiere)
    }
  })

  // Retourner seulement les domaines qui ont des matières
  return Object.entries(domaines)
    .filter(([_, matieres]) => matieres.length > 0)
    .map(([nom, matieres]) => ({ nom, matieres }))
}
export default function Bulletins() {
  const toast = useToast()
  const { etablissement } = useEtablissement()
  const [classes, setClasses] = useState([])
  const [etudiants, setEtudiants] = useState([])

  const [classeId, setClasseId] = useState('')
  const [etudiantId, setEtudiantId] = useState('')
  const [trimestre, setTrimestre] = useState(1)

  const [bulletin, setBulletin] = useState(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)
  const [downloadingClasse, setDownloadingClasse] = useState(false)

  useEffect(() => {
    classesApi.list({ per_page: 1000 }).then((res) => setClasses(res.data.data)).catch(() => {})
  }, [])

  useEffect(() => {
    if (!classeId) {
      setEtudiants([])
      setEtudiantId('')
      return
    }
    etudiantsApi
      .list({ classe_id: classeId, per_page: 1000 })
      .then((res) => {
        setEtudiants(res.data.data)
        setEtudiantId('')
      })
      .catch(() => {})
  }, [classeId])

  useEffect(() => {
    if (!etudiantId) {
      setBulletin(null)
      return
    }
    setLoading(true)
    setError(null)
    bulletinApi
      .show(etudiantId, trimestre === 0 ? { annuel: 1 } : { trimestre })
      .then((res) => setBulletin(res.data))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false))
  }, [etudiantId, trimestre])

  const handleDownloadPdf = () => {
    const el = document.getElementById('bulletin-print')
    if (!el) return
    const nom = bulletin?.etudiant ? `${bulletin.etudiant.nom}_${bulletin.etudiant.prenom}` : 'eleve'
    downloadElementPdf(el, `bulletin-${nom}.pdf`)
  }

  const handleDownloadClasse = async () => {
    if (!classeId) {
      toast.error('Choisissez d\'abord une classe.')
      return
    }
    setDownloadingClasse(true)
    try {
      const res = await bulletinApi.classe(
        classeId,
        trimestre === 0 ? { annuel: 1 } : { trimestre }
      )
      const { bulletins, classe } = res.data

      if (!bulletins || bulletins.length === 0) {
        toast.error('Aucun élève dans cette classe.')
        return
      }

      const trimestreLabel = TRIMESTRES_ANNUEL.find((t) => t.value === trimestre)?.label

      await downloadBulletinsPdf({
        bulletins,
        etablissement,
        trimestreLabel,
        filename: `bulletins-${classe.nom.replace(/[^a-zA-Z0-9]/g, '-')}.pdf`,
      })

      toast.success(`Bulletins de ${classe.nom} téléchargés (${bulletins.length} élève(s)).`)
    } catch (err) {
      toast.error(err.message)
    } finally {
      setDownloadingClasse(false)
    }
  }

  return (
    <div>
      <PageHeader
        title="Bulletins"
        subtitle="Consultez et imprimez les bulletins de notes."
        extra={
          <>
            <Select
              value={classeId}
              onChange={(e) => setClasseId(e.target.value)}
              className="filter-select"
              aria-label="Choisir une classe"
            >
              <option value="">— Classe —</option>
              {classes.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.nom}
                </option>
              ))}
            </Select>
            <Select
              value={etudiantId}
              onChange={(e) => setEtudiantId(e.target.value)}
              className="filter-select"
              aria-label="Choisir un élève"
            >
              <option value="">— Élève —</option>
              {etudiants.map((e) => (
                <option key={e.id} value={e.id}>
                  {e.prenom} {e.nom}
                </option>
              ))}
            </Select>
            <Select
              value={trimestre}
              onChange={(e) => setTrimestre(Number(e.target.value))}
              className="filter-select"
              aria-label="Choisir le trimestre"
            >
              {TRIMESTRES_ANNUEL.map((t) => (
                <option key={t.value} value={t.value}>
                  {t.label}
                </option>
              ))}
            </Select>
            <button
              type="button"
              className="btn btn--ghost"
              onClick={handleDownloadClasse}
              disabled={downloadingClasse || !classeId}
            >
              {downloadingClasse ? 'Génération…' : '📄 Tous les bulletins de la classe'}
            </button>
          </>
        }
      />

      {error && <Alert>{error}</Alert>}

      {!etudiantId ? (
        <EmptyState
          title="Sélectionnez un élève"
          description="Choisissez une classe, puis un élève et un trimestre pour afficher son bulletin."
        />
      ) : loading ? (
        <div className="page-loader">
          <Spinner />
        </div>
      ) : bulletin ? (
        <div className="bulletin-wrap">
          <div className="bulletin" id="bulletin-print">
            <div className="bulletin-watermark" aria-hidden="true">
              <img src="/logo.jpg" alt="Logo du lycée" className="bulletin-watermark-img" />
            </div>

            <div className="bulletin-header">
              <div className="bulletin-topline">
                <div className="bulletin-country-block">
                  <span className="bulletin-country">{ETABLISSEMENT.pays}</span>
                  <span className="bulletin-country-sub">Unité — Travail — Progrès</span>
                </div>

                <div className="bulletin-emblem" aria-hidden="true">
                  <img src="/logo.jpg" alt="Logo du lycée" className="bulletin-emblem-img" />
                </div>

                <div className="bulletin-country-block bulletin-country-block--right">
                  <span className="bulletin-country">NATION — ÉDUCATION</span>
                  <span className="bulletin-country-sub">Service public scolaire</span>
                </div>
              </div>

              <div className="bulletin-motto">{ETABLISSEMENT.slogan}</div>
              <div className="bulletin-ministry">{ETABLISSEMENT.ministere}</div>

              <div className="bulletin-school">
                <img src="/logo.jpg" alt="Logo du lycée" className="bulletin-logo-img" />
                <span className="bulletin-school-name">{ETABLISSEMENT.nom}</span>
              </div>

              <div className="bulletin-school-meta">{ETABLISSEMENT.adresse}</div>

              <div className="bulletin-year">
                Année scolaire {bulletin.etudiant.annee_scolaire || '2025-2026'}
              </div>

              <div className="bulletin-title">
                BULLETIN SCOLAIRE — {TRIMESTRES_ANNUEL.find((t) => t.value === trimestre)?.label}
              </div>

              <div className="bulletin-student-panel">
                <div className="bulletin-student-row">
                  <span className="student-label">Nom &amp; Prénoms</span>
                  <span className="student-value">
                    {bulletin.etudiant.prenom} {bulletin.etudiant.nom}
                  </span>
                </div>
                <div className="bulletin-student-row">
                  <span className="student-label">Classe</span>
                  <span className="student-value">{bulletin.classe?.nom || '—'}</span>
                </div>
                <div className="bulletin-student-row">
                  <span className="student-label">Niveau</span>
                  <span className="student-value">{getNiveauLabel(bulletin.classe?.niveau) || '—'}</span>
                </div>
                <div className="bulletin-student-row">
                  <span className="student-label">Matricule</span>
                  <span className="student-value">{bulletin.etudiant.matricule || '—'}</span>
                </div>
              </div>
            </div>

            {(bulletin.motifs_absences?.length > 0 ||
              bulletin.motifs_retards?.length > 0 ||
              bulletin.motifs_convocations?.length > 0) && (
              <div className="bulletin-vie-scolaire">
                <div className="bulletin-section-title">Vie scolaire</div>
                {bulletin.motifs_absences?.length > 0 && (
                  <p className="bulletin-motif">
                    <strong>Absences :</strong> {bulletin.motifs_absences.join(', ')}
                  </p>
                )}
                {bulletin.motifs_retards?.length > 0 && (
                  <p className="bulletin-motif">
                    <strong>Retards :</strong> {bulletin.motifs_retards.join(', ')}
                  </p>
                )}
                {bulletin.motifs_convocations?.length > 0 && (
                  <p className="bulletin-motif">
                    <strong>Convocations :</strong> {bulletin.motifs_convocations.join(', ')}
                  </p>
                )}
              </div>
            )}

            <div className="bulletin-section-title">Informations de l’élève</div>
            <div className="bulletin-identity-grid">
              <div className="identity-box">
                <span className="identity-label">Nom</span>
                <strong>{bulletin.etudiant.prenom} {bulletin.etudiant.nom}</strong>
              </div>
              <div className="identity-box">
                <span className="identity-label">Classe</span>
                <strong>{bulletin.classe?.nom || '—'}</strong>
              </div>
              <div className="identity-box">
                <span className="identity-label">Niveau</span>
                <strong>{getNiveauLabel(bulletin.classe?.niveau) || '—'}</strong>
              </div>
              <div className="identity-box">
                <span className="identity-label">Année scolaire</span>
                <strong>{bulletin.etudiant.annee_scolaire || '2025-2026'}</strong>
              </div>
            </div>

            <div className="bulletin-section-title">Matières</div>
            {groupMatieresByDomaine(bulletin.matieres).map((domaine) => (
              <div key={domaine.nom}>
                <h4 style={{ marginTop: '1.5rem', marginBottom: '0.75rem', fontSize: '0.95rem', fontWeight: '600', color: '#4b5563' }}>
                  {domaine.nom}
                </h4>
                <table className="table bulletin-table bulletin-main-table">
                  <thead>
                    <tr>
                      <th>Matières</th>
                      <th>Coef.</th>
                      <th>Moyenne</th>
                      <th>Moy. classe</th>
                      <th>Min</th>
                      <th>Max</th>
                      <th>Appréciation</th>
                    </tr>
                  </thead>
                  <tbody>
                    {domaine.matieres.map((row) => (
                      <tr key={row.matiere.id}>
                        <td>
                          <span className="matiere-cell">
                            <span
                              className="matiere-dot"
                              style={{ background: row.matiere.couleur || '#4f46e5' }}
                            />
                            {row.matiere.nom}
                          </span>
                        </td>
                        <td>{row.matiere.coefficient}</td>
                        <td className="cell-strong">{row.moyenne.toFixed(2)}</td>
                        <td className="cell-muted">
                          {row.moyenne_classe != null ? row.moyenne_classe.toFixed(2) : '—'}
                        </td>
                        <td className="cell-muted">{row.min}</td>
                        <td className="cell-muted">{row.max}</td>
                        <td className="cell-muted">{row.appreciation}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ))}

            <div className="bulletin-section-title">Résultat de l’élève</div>
            <table className="table bulletin-table bulletin-result-table">
              <thead>
                <tr>
                  <th>Résultat annuel</th>
                  <th>Profil de la classe</th>
                  <th>Travail de l’élève</th>
                  <th>Conduite</th>
                  <th>Décision</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>{bulletin.moyenne_generale ?? '—'}</td>
                  <td>{bulletin.rang ? `Rang ${bulletin.rang}/${bulletin.effectif}` : '—'}</td>
                  <td>{bulletin.appreciation_generale || '—'}</td>
                  <td>{bulletin.mention ? bulletin.mention.label : '—'}</td>
                  <td>{bulletin.mention ? 'Admis' : '—'}</td>
                </tr>
              </tbody>
            </table>

            <div className="bulletin-appreciation">
              <strong>Appréciation générale :</strong>{' '}
              {bulletin.appreciation_generale}
            </div>

            <div className="bulletin-summary">
              <div className="summary-item">
                <span className="summary-value">{bulletin.moyenne_generale ?? '—'}</span>
                <span className="summary-label">Moyenne générale</span>
              </div>
              <div className="summary-item">
                <span className="summary-value">{bulletin.rang ? `${bulletin.rang}ᵉ` : '—'}</span>
                <span className="summary-label">Rang / {bulletin.effectif}</span>
              </div>
              <div className="summary-item">
                {bulletin.mention ? (
                  <span className={`mention-badge mention-badge--${mentionColor(bulletin.mention.key)}`}>
                    {bulletin.mention.label}
                  </span>
                ) : (
                  '—'
                )}
                <span className="summary-label">Mention</span>
              </div>
              <div className="summary-item">
                <span className="summary-value">{bulletin.nb_absences ?? 0}</span>
                <span className="summary-label">Absences</span>
              </div>
              <div className="summary-item">
                <span className="summary-value">{bulletin.nb_retards ?? 0}</span>
                <span className="summary-label">Retards</span>
              </div>
              <div className="summary-item">
                <span className="summary-value">{bulletin.nb_convocations ?? 0}</span>
                <span className="summary-label">Convocations</span>
              </div>
            </div>

            <div className="bulletin-footer">
              <div className="signature-block">
                <span>Visa du parent</span>
              </div>
              <div className="signature-block">
                <span>Visa du directeur</span>
              </div>
              <div className="signature-block">
                <span>Fait le {new Date().toLocaleDateString('fr-FR')}</span>
              </div>
            </div>
          </div>

          <div className="bulletin-actions no-print">
            <button type="button" className="btn btn--ghost" onClick={() => window.print()}>
              🖨️ Imprimer
            </button>
            <button type="button" className="btn btn--primary" onClick={handleDownloadPdf}>
              📄 Télécharger PDF
            </button>
          </div>
        </div>
      ) : null}
    </div>
  )
}
