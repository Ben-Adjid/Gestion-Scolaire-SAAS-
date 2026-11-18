import axios from 'axios'

const client = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: {
    Accept: 'application/json',
  },
})

client.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

function normalizeError(error) {
  const response = error.response
  const normalized = new Error(
    response?.data?.message || error.message || 'Une erreur est survenue.'
  )
  normalized.status = response?.status
  normalized.errors = response?.data?.errors || {}
  normalized.response = response
  return normalized
}

client.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      const sessionActive = Boolean(localStorage.getItem('token'))
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      const pagePublique = ['/accueil', '/login'].includes(window.location.pathname)
      if (sessionActive && !pagePublique) {
        window.location.assign('/login')
      }
    }
    // Abonnement expiré ou suspendu : on envoie l'utilisateur vers la page de blocage.
    if (error.response?.status === 403 && error.response?.data?.code === 'subscription_inactive') {
      if (window.location.pathname !== '/abonnement-suspendu') {
        window.location.assign('/abonnement-suspendu')
      }
    }
    return Promise.reject(normalizeError(error))
  }
)

export default client
