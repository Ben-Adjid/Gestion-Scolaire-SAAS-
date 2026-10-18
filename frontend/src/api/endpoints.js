import client from './client'

export const authApi = {
  login: (credentials) => client.post('/login', credentials),
  me: () => client.get('/me'),
  logout: () => client.post('/logout'),
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
}

export const dossierInscriptionApi = {
  show: (etudiantId) => client.get(`/etudiants/${etudiantId}/dossier`),
  save: (etudiantId, data) => client.post(`/etudiants/${etudiantId}/dossier`, data),
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
  stats: () => client.get('/frais-scolaires/stats'),
  bulkInit: (data) => client.post('/frais-scolaires/bulk', data),
  create: (data) => client.post('/frais-scolaires', data),
  update: (id, data) => client.put(`/frais-scolaires/${id}`, data),
  remove: (id) => client.delete(`/frais-scolaires/${id}`),
  paiements: (id) => client.get(`/frais-scolaires/${id}/paiements`),
  addPaiement: (id, data) => client.post(`/frais-scolaires/${id}/paiements`, data),
}
