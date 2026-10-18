import { useCallback, useEffect, useRef, useState } from 'react'
import { classesApi, palmaresApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Modal from '../components/Modal'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Alert from '../components/Alert'
import { Select } from '../components/Field'
import { downloadTablePdf, downloadElementPdf } from '../utils/pdf'
import { mentionColor } from '../utils/format'
import { MEDALS, TRIMESTRES } from '../utils/constants'
import { useEtablissement } from '../context/EtablissementContext'

export default function Palmares() {
  const { etablissement } = useEtablissement()
  const [classes, setClasses] = useState([])
  const [cycle, setCycle] = useState('')
  const [classeId, setClasseId] = useState('')
  const [trimestre, setTrimestre] = useState(1)

  const [tableau, setTableau] = useState([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)
  const [attestation, setAttestation] = useState(null)

  useEffect(() => {
    classesApi.list({ per_page: 1000 }).then((res) => setClasses(res.data.data)).catch(() => {})
  }, [])

  const fetchPalmares = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await palmaresApi.index({
        trimestre,
        classe_id: classeId || undefined,
        cycle: classeId ? undefined : cycle || undefined,
      })
      setTableau(res.data.tableau_honneur)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [trimestre, cycle, classeId])

  const firstLoad = useRef(true)

  useEffect(() => {
    if (firstLoad.current) {
      firstLoad.current = false
      fetchPalmares()
    }
  }, [fetchPalmares])

  const openAttestation = (entry, classeNom) => {
    setAttestation({ entry, classeNom })
  }

  const handleDownloadPdf = () => {
    const columns = ['Rang', 'Élève', 'Classe', 'Moyenne', 'Mérite']
    const rows = []
    tableau.forEach((section) => {
      section.eleves.forEach((entry) => {
        rows.push([
          entry.rang,
          `${entry.etudiant.prenom} ${entry.etudiant.nom}`,
          section.classe.nom,
          Number(entry.moyenne).toFixed(2),
          entry.mention?.label || '',
        ])
      })
    })
    downloadTablePdf({
      title: "Tableau d'honneur",
      subtitle: `${TRIMESTRES.find((t) => t.value === trimestre)?.label} — ${new Date().toLocaleDateString('fr-FR')}`,
      columns,
      rows,
      filename: `tableau-honneur-${new Date().toISOString().slice(0, 10)}.pdf`,
    })
  }

  const handleDownloadAttestationPdf = () => {
    const el = document.getElementById('attestation-print')
    if (!el) return
    downloadElementPdf(el, `attestation-${attestation?.entry?.etudiant?.nom || 'eleve'}.pdf`)
  }

  return (
    <div>
      <PageHeader
        title="Tableau d'honneur"
        subtitle="Les meilleurs élèves, classés par moyenne."
        extra={
          <>
            <Select
              value={classeId}
              onChange={(e) => {
                setClasseId(e.target.value)
                setCycle('')
              }}
              className="filter-select"
              aria-label="Filtrer par classe"
            >
              <option value="">Toutes les classes</option>
              {classes.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.nom}
                </option>
              ))}
            </Select>
            <Select
              value={cycle}
              onChange={(e) => {
                setCycle(e.target.value)
                setClasseId('')
              }}
              className="filter-select"
              aria-label="Filtrer par cycle"
            >
              <option value="">Tous les cycles</option>
              <option value="college">Collège</option>
              <option value="lycee">Lycée</option>
            </Select>
            <Select
              value={trimestre}
              onChange={(e) => setTrimestre(Number(e.target.value))}
              className="filter-select"
              aria-label="Choisir le trimestre"
            >
              {TRIMESTRES.map((t) => (
                <option key={t.value} value={t.value}>
                  {t.label}
                </option>
              ))}
            </Select>
            <button type="button" className="btn btn--primary" onClick={fetchPalmares}>
              🔍 Afficher le tableau d'honneur
            </button>
            <button type="button" className="btn btn--ghost no-print" onClick={() => window.print()}>
              🖨️ Imprimer / PDF
            </button>
            <button type="button" className="btn btn--ghost no-print" onClick={handleDownloadPdf}>
              📄 Télécharger PDF
            </button>
          </>
        }
      />

      {error && <Alert>{error}</Alert>}

      {loading ? (
        <div className="page-loader">
          <Spinner />
        </div>
      ) : tableau.length === 0 ? (
        <EmptyState
          title="Aucun résultat"
          description="Sélectionnez une classe, un cycle et un trimestre, puis cliquez sur « Afficher le tableau d'honneur »."
        />
      ) : (
        <div className="card">
          <div className="card-body">
            <div className="table-wrapper">
              <table className="table">
                <thead>
                  <tr>
                    <th>Rang</th>
                    <th>Élève</th>
                    <th>Classe</th>
                    <th>Moyenne</th>
                    <th>Mérite</th>
                    <th className="th-actions">Attestation</th>
                  </tr>
                </thead>
                <tbody>
                  {tableau.flatMap((section) =>
                    section.eleves.map((entry) => (
                      <tr key={`${section.classe.id}-${entry.etudiant.id}`}>
                        <td>
                          <span className={`rank-badge rank-badge--${entry.rang}`}>
                            {entry.rang <= 3 ? MEDALS[entry.rang - 1] : `${entry.rang}ᵉ`}
                          </span>
                        </td>
                        <td className="cell-strong">
                          {entry.etudiant.prenom} {entry.etudiant.nom}
                        </td>
                        <td>
                          <span className="badge">{section.classe.nom}</span>
                        </td>
                        <td className="cell-strong">{Number(entry.moyenne || 0).toFixed(2)} /20</td>
                        <td>
                          {entry.mention ? (
                            <span className={`mention-badge mention-badge--${mentionColor(entry.mention.key)}`}>
                              {entry.mention.label}
                            </span>
                          ) : (
                            '—'
                          )}
                        </td>
                        <td className="cell-actions">
                          <button
                            type="button"
                            className="btn btn--ghost btn--sm no-print"
                            onClick={() => openAttestation(entry, section.classe.nom)}
                          >
                            🏆 Attestation
                          </button>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      <Modal
        open={Boolean(attestation)}
        title=""
        onClose={() => setAttestation(null)}
        size="xl"
        center
      >
        {attestation && (
          <div className="attestation" id="attestation-print">
            <div className="attestation-header-block">
              <div className="attestation-emblem">
                {etablissement.logo_path ? (
                  <img src={`/${etablissement.logo_path}`} alt="Logo" className="attestation-logo" />
                ) : (
                  <div className="attestation-emblem-inner" aria-label="Logo de l'établissement">
                    <svg viewBox="0 0 220 220" className="attestation-badge-svg">
                      <circle cx="110" cy="110" r="100" fill="none" stroke="#1d2433" strokeWidth="7" />
                      <circle cx="110" cy="110" r="80" fill="none" stroke="#1d2433" strokeWidth="2" />
                      <text x="110" y="72" textAnchor="middle" fontSize="20" fontWeight="700" fill="#1d2433" letterSpacing="2">LYCÉE</text>
                      <text x="110" y="95" textAnchor="middle" fontSize="18" fontWeight="700" fill="#1d2433" letterSpacing="2">IBNOU</text>
                      <text x="110" y="120" textAnchor="middle" fontSize="18" fontWeight="700" fill="#1d2433" letterSpacing="2">CINA</text>
                      <path d="M110 132 L155 165 L110 188 L65 165 Z" fill="#1d2433" />
                      <text x="110" y="170" textAnchor="middle" fontSize="26" fontWeight="800" fill="#fff">Y</text>
                    </svg>
                  </div>
                )}
              </div>

              <div className="attestation-head">
                <div className="attestation-country">RÉPUBLIQUE DU TCHAD</div>
                <div className="attestation-motto">Unité — Travail — Progrès</div>
                <div className="attestation-ministry">{etablissement.ministere}</div>
                <div className="attestation-school-wrap">
                  <div className="attestation-school-icon" aria-hidden="true">
                    <svg viewBox="0 0 220 220" className="attestation-school-svg">
                      <circle cx="110" cy="110" r="100" fill="none" stroke="#1d2433" strokeWidth="7" />
                      <circle cx="110" cy="110" r="80" fill="none" stroke="#1d2433" strokeWidth="2" />
                      <text x="110" y="72" textAnchor="middle" fontSize="20" fontWeight="700" fill="#1d2433" letterSpacing="2">LYCÉE</text>
                      <text x="110" y="95" textAnchor="middle" fontSize="18" fontWeight="700" fill="#1d2433" letterSpacing="2">IBNOU</text>
                      <text x="110" y="120" textAnchor="middle" fontSize="18" fontWeight="700" fill="#1d2433" letterSpacing="2">CINA</text>
                      <path d="M110 132 L155 165 L110 188 L65 165 Z" fill="#1d2433" />
                      <text x="110" y="170" textAnchor="middle" fontSize="26" fontWeight="800" fill="#fff">Y</text>
                    </svg>
                  </div>
                  <span className="attestation-school">{etablissement.nom}</span>
                </div>
              </div>
            </div>

            <div className="attestation-ribbon">
              <h2 className="attestation-title">ATTESTATION DE TABLEAU D'HONNEUR</h2>
            </div>

            <p className="attestation-text">Le Chef d'établissement atteste que l'élève</p>

            <div className="attestation-student">
              {attestation.entry.etudiant.prenom} {attestation.entry.etudiant.nom}
            </div>
            <div className="attestation-meta">
              Matricule : {attestation.entry.etudiant.matricule || '—'} — Classe : {attestation.classeNom}
            </div>

            <p className="attestation-text attestation-text--body">
              a été classé(e) <strong>{attestation.entry.rang}ᵉ</strong> de sa classe au{' '}
              <strong>{TRIMESTRES.find((t) => t.value === trimestre)?.label}</strong>, avec une moyenne
              de <strong>{Number(attestation.entry.moyenne || 0).toFixed(2)}/20</strong>
              {attestation.entry.mention ? (
                <>
                  {' '}et la mention <strong>« {attestation.entry.mention.label} »</strong>
                </>
              ) : (
                ''
              )}
              .
            </p>

            <div className="attestation-sign">
              <div className="attestation-date">
                Fait à {etablissement.nom}, le {new Date().toLocaleDateString('fr-FR')}
              </div>
              <div>
                <div className="attestation-sign-block">
                  <span className="attestation-sign-line" />
                  <span>
                    {etablissement.responsable
                      ? `Le Chef d'établissement, ${etablissement.responsable}`
                      : "Le Chef d'établissement"}
                  </span>
                </div>
              </div>
            </div>

            <div className="attestation-print-action no-print">
              <button type="button" className="btn btn--ghost btn--sm" onClick={() => window.print()}>
                🖨️ Imprimer
              </button>
              <button type="button" className="btn btn--primary btn--sm" onClick={handleDownloadAttestationPdf}>
                📄 Télécharger PDF
              </button>
            </div>
          </div>
        )}
      </Modal>
    </div>
  )
}
