import client from './client'

export const authApi = {
  login: (credentials) => client.post('/login', credentials),
  me: () => client.get('/me'),
  logout: () => client.post('/logout'),
}

export const motDePasseApi = {
  oublie: (email) => client.post('/mot-de-passe/oublie', { email }),
  reinitialiser: (data) => client.post('/mot-de-passe/reinitialiser', data),
  changer: (data) => client.post('/mot-de-passe/changer', data),
}

export const dashboardApi = {
  index: () => client.get('/dashboard'),
}

export const alertesApi = {
  list: () => client.get('/alertes', { params: { per_page: 50 } }),
  verifier: (id) => client.post(`/alertes/${id}/verifier`),
}

export const classesApi = {
  list: (params) => client.get('/classes', { params }),
  create: (data) => client.post('/classes', data),
  update: (id, data) => client.put(`/classes/${id}`, data),
  remove: (id) => client.delete(`/classes/${id}`),
}

export const enseignantsApi = {
  list: (params) => client.get('/enseignants', { params }),
  create: (data) => client.post('/enseignants', data),
  update: (id, data) => client.put(`/enseignants/${id}`, data),
  remove: (id) => client.delete(`/enseignants/${id}`),
}

export const etudiantsApi = {
  list: (params) => client.get('/etudiants', { params }),
  create: (data) => client.post('/etudiants', data),
  update: (id, data) => client.put(`/etudiants/${id}`, data),
  remove: (id) => client.delete(`/etudiants/${id}`),
  lookup: (matricule) => client.get('/etudiants/lookup', { params: { matricule } }),
  doublons: (params) => client.get('/etudiants/doublons', { params }),
  suivi: (id) => client.get(`/etudiants/${id}/suivi`),
  parents: (id) => client.get(`/etudiants/${id}/parents`),
  compteParent: (id, parent) => client.post(`/etudiants/${id}/compte-parent`, { parent }),
}

export const matieresApi = {
  list: (params) => client.get('/matieres', { params }),
  create: (data) => client.post('/matieres', data),
  update: (id, data) => client.put(`/matieres/${id}`, data),
  remove: (id) => client.delete(`/matieres/${id}`),
}

export const notesApi = {
  list: (params) => client.get('/notes', { params }),
  create: (data) => client.post('/notes', data),
  bulk: (data) => client.post('/notes/bulk', data),
  update: (id, data) => client.put(`/notes/${id}`, data),
  remove: (id) => client.delete(`/notes/${id}`),
}

export const inscriptionsApi = {
  list: (params) => client.get('/inscriptions', { params }),
  create: (data) => client.post('/inscriptions', data),
  update: (id, data) => client.put(`/inscriptions/${id}`, data),
  remove: (id) => client.delete(`/inscriptions/${id}`),
}

export const bulletinApi = {
  show: (id, params) => client.get(`/etudiants/${id}/bulletin`, { params }),
  classe: (classeId, params) => client.get(`/classes/${classeId}/bulletins`, { params }),
}

export const palmaresApi = {
  index: (params) => client.get('/palmares', { params }),
}

export const usersApi = {
  list: (params) => client.get('/users', { params }),
  create: (data) => client.post('/users', data),
  update: (id, data) => client.put(`/users/${id}`, data),
  remove: (id) => client.delete(`/users/${id}`),
}

export const emploiDuTempsApi = {
  list: (params) => client.get('/emploi-du-temps', { params }),
  creneaux: () => client.get('/creneaux'),
  create: (data) => client.post('/emploi-du-temps', data),
  update: (id, data) => client.put(`/emploi-du-temps/${id}`, data),
  remove: (id) => client.delete(`/emploi-du-temps/${id}`),
}

export const assistantApi = {
  chat: (message) => client.post('/assistant', { message }),
}

export const reglesApi = {
  show: () => client.get('/regles'),
  update: (data) => client.put('/regles', data),
}

export const absencesApi = {
  list: (params) => client.get('/absences', { params }),
  stats: () => client.get('/absences/stats'),
  create: (data) => client.post('/absences', data),
  update: (id, data) => client.put(`/absences/${id}`, data),
  remove: (id) => client.delete(`/absences/${id}`),
}

export const appelsApi = {
  show: (params) => client.get('/appels', { params }),
  save: (data) => client.post('/appels', data),
  confirmer: (id) => client.post(`/appels/${id}/confirmer`),
  enAttente: () => client.get('/appels/en-attente'),
}

export const convocationsApi = {
  list: (params) => client.get('/convocations', { params }),
  create: (data) => client.post('/convocations', data),
  remove: (id) => client.delete(`/convocations/${id}`),
}

export const pointagesApi = {
  aujourdhui: () => client.get('/pointages/aujourdhui'),
  store: (data) => client.post('/pointages', data),
  list: (params) => client.get('/pointages', { params }),
  stats: (params) => client.get('/pointages/stats', { params }),
}

export const absencesEnseignantsApi = {
  list: (params) => client.get('/absences-enseignants', { params }),
  stats: () => client.get('/absences-enseignants/stats'),
  create: (data) => client.post('/absences-enseignants', data),
  remove: (id) => client.delete(`/absences-enseignants/${id}`),
}

export const messagesApi = {
  list: (params) => client.get('/messages', { params }),
  stats: () => client.get('/messages/stats'),
  create: (data) => client.post('/messages', data),
  markRead: (id) => client.post(`/messages/${id}/lu`),
  fichier: (id) => client.get(`/messages/${id}/fichier`, { responseType: 'blob' }),
}

export const suiviApi = {
  enDifficulte: (params) => client.get('/eleves-difficulte', { params }),
}

export const examensApi = {
  index: () => client.get('/examens'),
}

export const dossiersApi = {
  index: (params) => client.get('/dossiers-examen', { params }),
  save: (etudiantId, data) => client.post(`/dossiers-examen/${etudiantId}`, data),
  fichier: (etudiantId, type) => client.get(`/dossiers-examen/${etudiantId}/fichiers/${type}`, { responseType: 'blob' }),
}

export const dossierInscriptionApi = {
  show: (etudiantId) => client.get(`/etudiants/${etudiantId}/dossier`),
  save: (etudiantId, data) => client.post(`/etudiants/${etudiantId}/dossier`, data),
  fichier: (etudiantId, type) => client.get(`/etudiants/${etudiantId}/dossier/fichiers/${type}`, { responseType: 'blob' }),
}

export const auditsApi = {
  index: (params) => client.get('/audits', { params }),
}

export const etablissementApi = {
  show: () => client.get('/etablissement'),
  update: (data) => client.post('/etablissement', data),
}

export const fraisScolairesApi = {
  list: (params) => client.get('/frais-scolaires', { params }),
  stats: (params) => client.get('/frais-scolaires/stats', { params }),
  bulkInit: (data) => client.post('/frais-scolaires/bulk', data),
  create: (data) => client.post('/frais-scolaires', data),
  update: (id, data) => client.put(`/frais-scolaires/${id}`, data),
  remove: (id) => client.delete(`/frais-scolaires/${id}`),
  paiements: (id) => client.get(`/frais-scolaires/${id}/paiements`),
  addPaiement: (id, data) => client.post(`/frais-scolaires/${id}/paiements`, data),
  relancer: (id) => client.post(`/frais-scolaires/${id}/relancer`),
}

export const conseilApi = {
  prepare: (classeId, trimestre) => client.get(`/classes/${classeId}/conseil`, { params: { trimestre } }),
  list: (classeId) => client.get(`/classes/${classeId}/conseils`),
  show: (classeId, id) => client.get(`/classes/${classeId}/conseils/${id}`),
  create: (classeId, data) => client.post(`/classes/${classeId}/conseils`, data),
}

export const exportApi = {
  fraisCsv: (annee) => client.get('/frais-scolaires/export', { params: { annee_scolaire: annee }, responseType: 'blob' }),
  recu: (paiementId) => client.get(`/paiements/${paiementId}/recu`, { responseType: 'blob' }),
}

export function telecharger(blob, nomFichier) {
  const url = URL.createObjectURL(blob)
  const lien = document.createElement('a')
  lien.href = url
  lien.download = nomFichier
  document.body.appendChild(lien)
  lien.click()
  lien.remove()
  window.setTimeout(() => URL.revokeObjectURL(url), 1000)
}

export const espaceParentApi = {
  enfants: () => client.get('/parent/enfants'),
  resume: (id) => client.get(`/parent/enfants/${id}/resume`),
  justifier: (etudiantId, absenceId, motif) =>
    client.post(`/parent/enfants/${etudiantId}/absences/${absenceId}/justifier`, { motif }),
  mesJustifications: () => client.get('/parent/justifications'),
  preferences: () => client.get('/parent/preferences-notifications'),
  enregistrerPreferences: (preferences) => client.put('/parent/preferences-notifications', { preferences }),
  sorties: () => client.get('/parent/sorties'),
  signerSortie: (etudiantId, data) => client.post(`/parent/enfants/${etudiantId}/sorties`, data),
  annulerSortie: (id) => client.post(`/parent/sorties/${id}/annuler`),
  documentSortie: (id) => client.get(`/parent/sorties/${id}/document`, { responseType: 'blob' }),
}

// Pays pris en charge (en-tête officiel, monnaie, indicatif) : référentiel public.
export const paysApi = {
  liste: () => client.get('/pays'),
}

// Autorisations de sortie signées par les parents : traitement par la vie scolaire.
export const autorisationsSortieApi = {
  liste: (params) => client.get('/autorisations-sortie', { params }),
  decider: (id, decision, commentaire) => client.post(`/autorisations-sortie/${id}/decision`, { decision, commentaire }),
  sortie: (id) => client.post(`/autorisations-sortie/${id}/sortie`),
  document: (id) => client.get(`/autorisations-sortie/${id}/document`, { responseType: 'blob' }),
}

/** Ouvre un document HTML (reçu, autorisation…) dans un nouvel onglet, prêt à imprimer. */
export function ouvrirDocument(blob) {
  const url = URL.createObjectURL(new Blob([blob], { type: 'text/html' }))
  const fenetre = window.open(url, '_blank', 'noopener')
  setTimeout(() => URL.revokeObjectURL(url), 60000)
  return fenetre
}

// Décision du CPE / direction sur une demande de justification.
export const justificationsApi = {
  liste: (statut) => client.get('/justifications', { params: statut ? { statut } : {} }),
  decider: (id, decision, commentaire) => client.post(`/justifications/${id}/decision`, { decision, commentaire }),
}

// Formule Entreprise : clés d'accès à l'API.
export const apiTokensApi = {
  liste: () => client.get('/api-tokens'),
  creer: (name) => client.post('/api-tokens', { name }),
  revoquer: (id) => client.delete(`/api-tokens/${id}`),
}

// Formule Entreprise : support prioritaire.
export const supportApi = {
  liste: () => client.get('/support'),
  envoyer: (data) => client.post('/support', data),
}

// Formules VIP et Entreprise : analyses avancées.
export const analysesApi = {
  index: () => client.get('/analyses'),
}

// Console super admin : abonnements et support.
export const abonnementsApi = {
  renouveler: (etablissementId, data) => client.post(`/super-admin/etablissements/${etablissementId}/renouveler`, data),
  paiements: (etablissementId) => client.get(`/super-admin/etablissements/${etablissementId}/paiements-abonnement`),
  recu: (paiementId) => client.get(`/super-admin/paiements-abonnement/${paiementId}/recu`, { responseType: 'blob' }),
  demandes: () => client.get('/super-admin/demandes-abonnement'),
  justificatifDemande: (id) => client.get(`/super-admin/demandes-abonnement/${id}/justificatif`, { responseType: 'blob' }),
  approuverDemande: (id) => client.post(`/super-admin/demandes-abonnement/${id}/approuver`),
  refuserDemande: (id, motif_refus) => client.post(`/super-admin/demandes-abonnement/${id}/refuser`, { motif_refus }),
}

export const renouvellementEtablissementApi = {
  options: () => client.get('/abonnement/options'),
  demandes: () => client.get('/abonnement/demandes'),
  creer: (data) => client.post('/abonnement/demandes', data),
}

export const supportPlateformeApi = {
  liste: () => client.get('/super-admin/support'),
  repondre: (id, data) => client.post(`/super-admin/support/${id}`, data),
}
