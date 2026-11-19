import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { espaceParentApi } from '../api/endpoints'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Alert from '../components/Alert'
import Modal from '../components/Modal'
import { useToast } from '../components/Toast'
import { Field, Textarea } from '../components/Field'
import { useAuth } from '../context/AuthContext'
import { useEtablissement } from '../context/EtablissementContext'
import { lienWhatsApp } from '../utils/whatsapp'
import FilResume from '../components/FilResume'
import { formatMontant } from '../utils/pays'
import RecuApercu from '../components/RecuApercu'

const ONGLETS = [
  { id: 'presences', label: 'Présences', icone: '' },
  { id: 'notes', label: 'Notes', icone: '' },
  { id: 'bulletins', label: 'Bulletins', icone: '' },
  { id: 'examens', label: 'Examens', icone: '' },
  { id: 'convocations', label: 'Convocations', icone: '' },
  { id: 'frais', label: 'Frais', icone: '' },
]

const montant = formatMontant

const initiales = (en) => `${en.prenom?.[0] || ''}${en.nom?.[0] || ''}`.toUpperCase()

// Moyenne simple des notes validées, sur 20 (absente si aucune note).
function moyenneDesNotes(notes) {
  if (!notes?.length) return null
  const total = notes.reduce((somme, n) => somme + Number(n.valeur || 0), 0)
  return (total / notes.length).toFixed(2)
}

export default function EspaceParent() {
  const toast = useToast()
  const { user } = useAuth()
  const { etablissement } = useEtablissement()
  const [apercuRecu, setApercuRecu] = useState(null)
  const [enfants, setEnfants] = useState([])
  const [enfantId, setEnfantId] = useState(null)
  const [resume, setResume] = useState(null)
  const [onglet, setOnglet] = useState('presences')
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState(null)
  const [demande, setDemande] = useState(null)
  const [motif, setMotif] = useState('')
  const [envoi, setEnvoi] = useState(false)


  useEffect(() => {
    espaceParentApi.enfants()
      .then((res) => {
        setEnfants(res.data.enfants)
        if (res.data.enfants.length) setEnfantId(res.data.enfants[0].id)
      })
      .catch((e) => setErreur(e.message))
      .finally(() => setChargement(false))
  }, [])

  useEffect(() => {
    if (!enfantId) return
    setResume(null)
    espaceParentApi.resume(enfantId).then((res) => setResume(res.data)).catch((e) => setErreur(e.message))
  }, [enfantId])

  // Suivi en quasi temps réel : les notes, absences et frais sont rafraîchis toutes les 30 secondes.
  useEffect(() => {
    if (!enfantId) return undefined
    const timer = setInterval(() => {
      espaceParentApi.resume(enfantId).then((res) => setResume(res.data)).catch(() => {})
    }, 30000)
    return () => clearInterval(timer)
  }, [enfantId])

  const choisirEnfant = (id) => {
    setEnfantId(id)
    setOnglet('presences')
  }

  const enfantCourant = useMemo(() => enfants.find((e) => e.id === enfantId), [enfants, enfantId])

  const indicateurs = useMemo(() => {
    if (!resume) return null
    const nonJustifiees = resume.presences.liste.filter((a) => a.type !== 'retard' && !a.justifie).length
    const resteAPayer = resume.frais.reduce((somme, f) => somme + Number(f.reste || 0), 0)
    return {
      absencesNonJustifiees: nonJustifiees,
      retards: resume.presences.retards,
      moyenne: moyenneDesNotes(resume.notes),
      resteAPayer,
    }
  }, [resume])

  const envoyerDemande = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    try {
      await espaceParentApi.justifier(enfantId, demande.id, motif)
      toast.success('Votre demande a été transmise au CPE.')
      setDemande(null)
      setMotif('')
      espaceParentApi.resume(enfantId).then((res) => setResume(res.data))
    } catch (err) {
      toast.error(err.message)
    } finally {
      setEnvoi(false)
    }
  }

  if (chargement) return <Spinner />

  return (
    <div className="parent">
      <header className="parent-entete">
        <div>
          <span className="parent-salutation">Bonjour {user?.name?.split(' ')[0]}</span>
          <h1>Suivi de la scolarité</h1>
        </div>
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          <Link to="/chatboard" className="btn btn--primary">Écrire à l’administration</Link>
          {lienWhatsApp(etablissement.telephone) && (
            <a className="btn btn--ghost" href={lienWhatsApp(etablissement.telephone, 'Bonjour, je suis parent d’un élève de votre établissement. ')} target="_blank" rel="noopener noreferrer">WhatsApp de l’école</a>
          )}
        </div>
      </header>

      {erreur && <Alert>{erreur}</Alert>}

      {enfants.length === 0 ? (
        <EmptyState
          title="Aucun enfant rattaché"
          description="Rapprochez-vous du secrétariat pour que votre compte soit relié à vos enfants."
        />
      ) : (
        <>
          <div className="parent-enfants" role="tablist" aria-label="Choisir un enfant">
            {enfants.map((en) => (
              <button
                key={en.id}
                type="button"
                role="tab"
                aria-selected={en.id === enfantId}
                className={`parent-enfant ${en.id === enfantId ? 'parent-enfant--actif' : ''}`}
                onClick={() => choisirEnfant(en.id)}
              >
                <span className="parent-enfant-avatar">{initiales(en)}</span>
                <span className="parent-enfant-texte">
                  <strong>{en.prenom} {en.nom}</strong>
                  <span className="parent-enfant-classe">{en.classe?.nom || 'Sans classe'}</span>
                  <small>{en.matricule}</small>
                </span></button>
            ))}
          </div>

          {!resume ? <Spinner /> : (
            <>
              <section className="parent-resume">
                <div className="parent-resume-titre">
                  <h2>{enfantCourant?.prenom} {enfantCourant?.nom}</h2>
                  <span className="parent-badge">{resume.classe?.nom || 'Sans classe'}</span>
                </div>
                <div className="parent-kpis">
                  <div className={`parent-kpi ${indicateurs.absencesNonJustifiees > 0 ? 'parent-kpi--alerte' : 'parent-kpi--ok'}`}>
                    <span>Absences non justifiées</span>
                    <strong>{indicateurs.absencesNonJustifiees}</strong>
                  </div>
                  <div className="parent-kpi">
                    <span>Retards</span>
                    <strong>{indicateurs.retards}</strong>
                  </div>
                  <div className="parent-kpi">
                    <span>Moyenne des notes</span>
                    <strong>{indicateurs.moyenne ? `${indicateurs.moyenne}/20` : '—'}</strong>
                  </div>
                  <div className={`parent-kpi ${indicateurs.resteAPayer > 0 ? 'parent-kpi--alerte' : 'parent-kpi--ok'}`}>
                    <span>Reste à payer</span>
                    <strong>{montant(indicateurs.resteAPayer)}</strong>
                  </div>
                </div>
              </section>

              <nav className="parent-onglets" aria-label="Rubriques">
                {ONGLETS.map((o) => (
                  <button
                    key={o.id}
                    type="button"
                    className={`parent-onglet ${onglet === o.id ? 'parent-onglet--actif' : ''}`}
                    onClick={() => setOnglet(o.id)}
                  >
                    <span aria-hidden="true">{o.icone}</span> {o.label}
                  </button>
                ))}
              </nav>

              <section className="card parent-panneau">
                <div className="card-body">
                  {onglet === 'presences' && (
                    resume.presences.liste.length === 0
                      ? <p className="muted">Aucune absence signalée. Bravo !</p>
                      : (
                        <div className="table-wrapper">
                          <table className="table">
                            <thead><tr><th>Date</th><th>Type</th><th>Statut</th><th /></tr></thead>
                            <tbody>
                              {resume.presences.liste.map((a) => (
                                <tr key={a.id}>
                                  <td>{a.date}</td>
                                  <td>{a.type === 'retard' ? 'Retard' : 'Absence'}</td>
                                  <td>
                                    <span className={`badge ${a.justifie ? 'badge--success' : 'badge--danger'}`}>
                                      {a.justifie ? 'Justifiée' : 'Non justifiée'}
                                    </span>
                                  </td>
                                  <td>
                                    {!a.justifie && (
                                      <button type="button" className="btn btn--ghost btn--sm" onClick={() => setDemande(a)}>
                                        Demander une justification
                                      </button>
                                    )}
                                  </td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                      )
                  )}

                  {onglet === 'examens' && (
                    resume.examens.length === 0
                      ? <p className="muted">Aucune épreuve planifiée pour cette classe.</p>
                      : (
                        <div className="parent-bulletins">
                          {resume.examens.map((e, i) => (
                            <article key={i} className="parent-bulletin">
                              <span className="parent-bulletin-trimestre">
                                {new Date(e.date).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })}
                              </span>
                              <strong>{e.matiere}</strong>
                              <span>{e.heure} · {e.duree_minutes} min{e.salle ? ` · salle ${e.salle}` : ''}</span>
                            </article>
                          ))}
                        </div>
                      )
                  )}

                  {onglet === 'convocations' && (
                    resume.convocations.length === 0
                      ? <p className="muted">Aucune convocation.</p>
                      : (
                        <ul className="top-list">
                          {resume.convocations.map((c, i) => (
                            <li key={i} className="top-item">
                              <span className="top-name">{c.date}</span>
                              <span>{c.motif}</span>
                            </li>
                          ))}
                        </ul>
                      )
                  )}

                  {onglet === 'notes' && (
                    resume.notes.length === 0
                      ? <p className="muted">Aucune note validée pour le moment.</p>
                      : (
                        <div className="table-wrapper">
                          <table className="table">
                            <thead><tr><th>Matière</th><th>Trimestre</th><th>Type</th><th>Note</th></tr></thead>
                            <tbody>
                              {resume.notes.map((n, i) => (
                                <tr key={i}><td>{n.matiere}</td><td>{n.trimestre}</td><td>{n.type}</td><td><strong>{n.valeur}/20</strong></td></tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                      )
                  )}

                  {onglet === 'bulletins' && (
                    resume.bulletins.length === 0
                      ? <p className="muted">Aucun bulletin publié pour le moment : il devient disponible après le conseil de classe.</p>
                      : (
                        <div className="parent-bulletins">
                          {resume.bulletins.map((b) => (
                            <article key={b.trimestre} className="parent-bulletin">
                              <span className="parent-bulletin-trimestre">Trimestre {b.trimestre}</span>
                              <strong className="parent-bulletin-moyenne">{b.moyenne_generale}/20</strong>
                              <span>Rang : {b.rang ?? '—'}</span>
                              <span className="muted">{b.decision?.label}</span>
                            </article>
                          ))}
                        </div>
                      )
                  )}

                  {onglet === 'frais' && (
                    resume.frais.length === 0
                      ? <p className="muted">Aucun frais enregistré.</p>
                      : (
                        <div className="table-wrapper">
                          <table className="table">
                            <thead><tr><th>Frais</th><th>Montant</th><th>Payé</th><th>Reste</th><th>Reçus</th></tr></thead>
                            <tbody>
                              {resume.frais.map((f, i) => (
                                <tr key={i}>
                                  <td>{f.libelle} ({f.annee_scolaire})</td>
                                  <td>{montant(f.montant)}</td>
                                  <td>{montant(f.paye)}</td>
                                  <td className={Number(f.reste) > 0 ? 'cell-danger' : ''}>{montant(f.reste)}</td>
                                  <td>
                                    {(f.paiements || []).length === 0 && <span className="muted">—</span>}
                                    {(f.paiements || []).map((p) => (
                                      <button
                                        key={p.id}
                                        type="button"
                                        className="btn btn--ghost btn--sm"
                                        title={`Reçu du ${p.date_paiement}`}
                                        onClick={() => setApercuRecu({
                                          paiement: { id: p.id, date_paiement: p.date_paiement, montant: p.montant, mode_paiement: p.mode_paiement, reference: p.reference, enregistre_le: p.enregistre_le, agent: p.agent ? { name: p.agent } : null },
                                          frais: { libelle: f.libelle, annee_scolaire: f.annee_scolaire, montant_total: f.montant, montant_paye: f.paye, reste: f.reste },
                                          eleve: { prenom: resume.eleve?.prenom, nom: resume.eleve?.nom, matricule: resume.eleve?.matricule, classe: resume.classe?.nom, niveau: resume.classe?.niveau },
                                          etablissement: { ...etablissement, monnaie: etablissement?.pays_info?.monnaie },
                                        })}
                                      >
                                        Voir le reçu
                                      </button>
                                    ))}
                                  </td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                      )
                  )}
                </div>
              </section>
            </>
          )}
        </>
      )}

      <FilResume limite={4} titre="Actualités de l’école" />

      <RecuApercu recu={apercuRecu} onClose={() => setApercuRecu(null)} />

      <Modal
        open={Boolean(demande)}
        title="Demander une justification"
        onClose={() => setDemande(null)}
        footer={
          <>
            <button type="button" className="btn btn--ghost" onClick={() => setDemande(null)} disabled={envoi}>Annuler</button>
            <button type="submit" form="demande-justification" className="btn btn--primary" disabled={envoi || !motif.trim()}>
              {envoi ? 'Envoi…' : 'Envoyer au CPE'}
            </button>
          </>
        }
      >
        <form id="demande-justification" onSubmit={envoyerDemande}>
          <p className="muted">Absence du {demande?.date}. Le CPE examinera votre demande.</p>
          <Field label="Motif" htmlFor="motif-justif" required>
            <Textarea
              id="motif-justif"
              rows={4}
              value={motif}
              onChange={(e) => setMotif(e.target.value)}
              placeholder="Ex : rendez-vous médical, deuil, maladie…"
            />
          </Field>
        </form>
      </Modal>
    </div>
  )
}
