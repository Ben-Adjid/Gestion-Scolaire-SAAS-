import { createContext, useContext, useEffect, useMemo, useState } from 'react'
import { etablissementApi } from '../api/endpoints'
import { useAuth } from './AuthContext'
import { definirPays } from '../utils/pays'

const EtablissementContext = createContext(null)

// Valeurs neutres affichées avant la connexion : aucune école n'est codée en dur.
const DEFAULTS = {
  nom: 'Espace scolaire',
  devise: null,
  ministere: null,
  annee_scolaire: null,
  logo_path: null,
  couleur_principale: null,
}

export function EtablissementProvider({ children }) {
  const { user } = useAuth()
  const userId = user?.id ?? null
  const [etablissement, setEtablissement] = useState(DEFAULTS)

  // La formule dépend de l'utilisateur connecté : on recharge à chaque connexion
  // et on revient aux valeurs par défaut à la déconnexion.
  useEffect(() => {
    if (!userId) {
      setEtablissement(DEFAULTS)
      return undefined
    }

    let actif = true
    etablissementApi
      .show()
      .then((res) => {
        if (actif && res.data) setEtablissement(res.data)
      })
      .catch(() => {})

    return () => {
      actif = false
    }
  }, [userId])

  // Pays de l'école : monnaie, en-tête des bulletins, indicatif. Défini avant le rendu des pages enfants.
  definirPays(etablissement.pays_info)

  // Marque blanche : l'onglet du navigateur porte le nom de l'établissement, sans aucune mention de la plateforme.
  useEffect(() => {
    document.title = etablissement.nom || 'Espace scolaire'
  }, [etablissement.nom])

  // Thème de l'établissement : tous ses utilisateurs partagent la même couleur.
  // Couleur choisie (formule Entreprise), sinon couleur nationale du pays de l'école, sinon la couleur par défaut.
  useEffect(() => {
    const racine = document.documentElement
    const couleur = etablissement.couleur_principale || etablissement.pays_info?.couleur
    if (couleur && /^#[0-9a-f]{6}$/i.test(couleur)) {
      racine.style.setProperty('--primary', couleur)
      racine.style.setProperty('--sidebar-active', couleur)
    } else {
      racine.style.removeProperty('--primary')
      racine.style.removeProperty('--sidebar-active')
    }
  }, [etablissement.couleur_principale, etablissement.pays_info?.couleur])

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
