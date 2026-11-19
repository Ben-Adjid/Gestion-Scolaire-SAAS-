// Service worker : l'application s'ouvre hors ligne (coquille et ressources déjà vues).
// Les données scolaires (API) et les fichiers téléversés ne sont JAMAIS mis en cache : elles viennent toujours du réseau.
const VERSION = 'ecole-v4'
const COQUILLE = ['/', '/manifest.webmanifest', '/favicon.svg', '/icons/icon-192.png', '/icons/icon-512.png']

// Les fichiers générés par le build (scripts, styles) sont lus depuis index.html et mis en cache dès l'installation,
// pour que l'application s'ouvre hors ligne dès la première visite complète.
async function ressourcesDuBuild() {
  const reponse = await fetch('/', { cache: 'no-store' })
  const html = await reponse.text()
  const trouves = html.match(/(?:src|href)="(\/assets\/[^"]+)"/g) || []
  return trouves.map((m) => m.match(/"(\/assets\/[^"]+)"/)[1])
}

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(VERSION)
      .then(async (cache) => {
        const build = await ressourcesDuBuild().catch(() => [])
        await cache.addAll([...COQUILLE, ...build])
      })
      .then(() => self.skipWaiting())
  )
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((cles) => Promise.all(cles.filter((c) => c !== VERSION).map((c) => caches.delete(c))))
      .then(() => self.clients.claim())
  )
})

function estRessourceSensible(url) {
  return url.pathname.startsWith('/api/') || url.pathname.startsWith('/uploads/') || url.pathname.startsWith('/storage/')
}

self.addEventListener('fetch', (event) => {
  const requete = event.request
  const url = new URL(requete.url)

  // Seules les requêtes GET du même site sont gérées ; le reste passe directement au réseau.
  if (requete.method !== 'GET' || url.origin !== self.location.origin || estRessourceSensible(url)) {
    return
  }

  event.respondWith(
    fetch(requete)
      .then((reponse) => {
        // On ne garde que les réponses réussies, et jamais les réponses partielles.
        if (reponse.ok && reponse.type === 'basic') {
          const copie = reponse.clone()
          caches.open(VERSION).then((cache) => cache.put(requete.url, copie))
        }
        return reponse
      })
      .catch(async () => {
        // Recherche par adresse seule : les en-têtes de négociation (Accept) varient selon l'appelant.
        const trouve = await caches.match(requete.url, { ignoreVary: true })
        if (trouve) return trouve
        // Hors ligne, une page demandée (navigation) ouvre la coquille ; une ressource manquante reste manquante.
        if (requete.mode === 'navigate') {
          return (await caches.match('/', { ignoreVary: true })) || Response.error()
        }
        return Response.error()
      })
  )
})
