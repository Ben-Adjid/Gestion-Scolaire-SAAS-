// Mode hors ligne pour l'appel : file d'attente locale et cache minimal des classes et des élèves.
// Les données restent sur l'appareil de l'utilisateur connecté et sont effacées à la déconnexion.
import { appelsApi } from '../api/endpoints'

const PREFIXE = 'hors-ligne:'
export const EVENEMENT_FILE = 'hors-ligne:file-modifiee'

const utilisateur = () => {
  try {
    return JSON.parse(localStorage.getItem('user') || 'null')?.id ?? 'anonyme'
  } catch {
    return 'anonyme'
  }
}

const cle = (nom) => `${PREFIXE}${utilisateur()}:${nom}`

const lire = (nom, defaut) => {
  try {
    const valeur = localStorage.getItem(cle(nom))
    return valeur ? JSON.parse(valeur) : defaut
  } catch {
    return defaut
  }
}

const ecrire = (nom, valeur) => {
  try {
    localStorage.setItem(cle(nom), JSON.stringify(valeur))
  } catch {
    // Stockage plein ou bloqué : le mode hors ligne est simplement indisponible.
  }
}

/** Vrai si l'erreur vient d'une absence de réseau (aucune réponse du serveur). */
export const estErreurReseau = (err) => !navigator.onLine || (err && !err.response && !err.status)

// ---------- Cache des classes et des élèves (lecture seule, minimal) ----------

export const memoriserClasses = (classes) =>
  ecrire('classes', classes.map((c) => ({ id: c.id, nom: c.nom, niveau: c.niveau })))

export const classesMemorisees = () => lire('classes', [])

export const memoriserEleves = (classeId, eleves) =>
  ecrire(`eleves:${classeId}`, eleves.map((e) => ({ id: e.id, nom: e.nom, prenom: e.prenom, matricule: e.matricule })))

export const elevesMemorises = (classeId) => lire(`eleves:${classeId}`, [])

// ---------- File d'attente des appels ----------

export const appelsEnAttente = () => lire('appels', [])

const annoncer = () => window.dispatchEvent(new Event(EVENEMENT_FILE))

/** Garde l'appel sur l'appareil. Un nouvel appel de la même classe et du même jour remplace l'ancien. */
export const mettreEnAttente = (appel) => {
  const file = appelsEnAttente().filter((a) => !(a.classe_id === appel.classe_id && a.date === appel.date))
  file.push({ ...appel, enregistre_le: new Date().toISOString() })
  ecrire('appels', file)
  annoncer()
}

/**
 * Envoie les appels en attente, dans l'ordre. S'arrête à la première coupure réseau.
 * Un appel refusé par le serveur (appel déjà validé, droits…) est retiré et signalé.
 */
export async function synchroniser() {
  const file = appelsEnAttente()
  const resultat = { envoyes: 0, refuses: [], restants: 0 }

  for (let i = 0; i < file.length; i += 1) {
    const { enregistre_le: _ignore, ...appel } = file[i]
    try {
      await appelsApi.save(appel)
      resultat.envoyes += 1
    } catch (err) {
      // Coupure réseau ou session expirée (401) : on garde l'appel, il repartira après reconnexion.
      if (estErreurReseau(err) || err?.status === 401) {
        ecrire('appels', file.slice(i))
        resultat.restants = file.length - i
        annoncer()
        return resultat
      }
      resultat.refuses.push({ appel, message: err.message })
    }
  }

  ecrire('appels', [])
  annoncer()
  return resultat
}

/**
 * À la déconnexion : efface les listes de classes et d'élèves gardées sur l'appareil.
 * Les appels pas encore envoyés sont conservés (rangés par utilisateur) et partiront à sa prochaine connexion.
 */
export function effacerDonneesHorsLigne() {
  try {
    Object.keys(localStorage)
      .filter((k) => k.startsWith(PREFIXE) && !k.endsWith(':appels'))
      .forEach((k) => localStorage.removeItem(k))
  } catch {
    // Rien à effacer.
  }
}
