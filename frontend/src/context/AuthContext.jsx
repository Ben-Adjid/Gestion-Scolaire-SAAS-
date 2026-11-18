import { createContext, useContext, useEffect, useMemo, useState } from 'react'
import { authApi } from '../api/endpoints'
import { effacerDonneesHorsLigne } from '../utils/horsLigne'

const AuthContext = createContext(null)

function parseStoredUser() {
  try {
    const stored = localStorage.getItem('user')
    if (!stored || stored === 'undefined') return null
    return JSON.parse(stored)
  } catch {
    return null
  }
}

export function AuthProvider({ children }) {
  const [user, setUser] = useState(parseStoredUser)
  const [loading, setLoading] = useState(Boolean(localStorage.getItem('token')))

  useEffect(() => {
    if (!localStorage.getItem('token')) return
    authApi
      .me()
      .then((res) => {
        setUser(res.data)
        localStorage.setItem('user', JSON.stringify(res.data))
      })
      .catch(() => {
        localStorage.removeItem('token')
        localStorage.removeItem('user')
        setUser(null)
      })
      .finally(() => setLoading(false))
  }, [])

  const login = async (credentials) => {
    const res = await authApi.login(credentials)
    // Double authentification : le serveur attend le code à six chiffres avant d'ouvrir la session.
    if (res.status === 202 && res.data?.requires_2fa) {
      return { requires2fa: true }
    }
    localStorage.setItem('token', res.data.token)
    if (res.data.user) {
      localStorage.setItem('user', JSON.stringify(res.data.user))
      setUser(res.data.user)
    }
    return res.data.user
  }

  // Ouvre la session à partir d'un jeton déjà émis (connexion par téléphone).
  const ouvrirSession = ({ token, user: utilisateur }) => {
    localStorage.setItem('token', token)
    localStorage.setItem('user', JSON.stringify(utilisateur))
    setUser(utilisateur)
    return utilisateur
  }

  const logout = async () => {
    try {
      await authApi.logout()
    } catch {
      // ignore logout errors (token may already be invalid)
    }
    // Listes de classes et d'élèves gardées pour le mode hors ligne : effacées de l'appareil.
    effacerDonneesHorsLigne()
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    setUser(null)
  }

  const value = useMemo(
    () => ({ user, loading, login, logout, ouvrirSession }),
    [user, loading]
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) {
    throw new Error('useAuth doit être utilisé dans un AuthProvider')
  }
  return context
}
