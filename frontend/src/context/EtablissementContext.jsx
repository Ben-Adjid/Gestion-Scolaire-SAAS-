import { createContext, useContext, useEffect, useMemo, useState } from 'react'
import { etablissementApi } from '../api/endpoints'

const EtablissementContext = createContext(null)

const DEFAULTS = {
  nom: 'Lycée Ibnou Cina',
  devise: 'Unité — Travail — Progrès',
  ministere: "Ministère de l'Éducation Nationale",
  annee_scolaire: '2025-2026',
}

export function EtablissementProvider({ children }) {
  const [etablissement, setEtablissement] = useState(DEFAULTS)

  useEffect(() => {
    etablissementApi
      .show()
      .then((res) => setEtablissement(res.data || DEFAULTS))
      .catch(() => {})
  }, [])

  const value = useMemo(
    () => ({
      etablissement,
      setEtablissement,
    }),
    [etablissement]
  )

  return (
    <EtablissementContext.Provider value={value}>{children}</EtablissementContext.Provider>
  )
}

export function useEtablissement() {
  const context = useContext(EtablissementContext)
  if (!context) {
    throw new Error('useEtablissement doit être utilisé dans un EtablissementProvider')
  }
  return context
}
