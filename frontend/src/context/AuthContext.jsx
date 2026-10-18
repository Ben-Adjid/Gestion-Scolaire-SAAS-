import { createContext, useContext, useEffect, useMemo, useState } from 'react'
import { authApi } from '../api/endpoints'

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
    localStorage.setItem('token', res.data.token)
    if (res.data.user) {
      localStorage.setItem('user', JSON.stringify(res.data.user))
      setUser(res.data.user)
    }
    return res.data.user
  }

  const logout = async () => {
    try {
      await authApi.logout()
    } catch {
      // ignore logout errors (token may already be invalid)
    }
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    setUser(null)
  }

  const value = useMemo(
    () => ({ user, loading, login, logout }),
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
