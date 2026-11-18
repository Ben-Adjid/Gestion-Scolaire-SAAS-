// Service worker minimal : l'application s'ouvre hors ligne (coquille), sans jamais mettre en cache les données.
const CACHE = 'ecole-coquille-v1'
const COQUILLE = ['/', '/manifest.webmanifest', '/favicon.svg']

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(COQUILLE)).then(() => self.skipWaiting()))
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((cles) => Promise.all(cles.filter((c) => c !== CACHE).map((c) => caches.delete(c))))
      .then(() => self.clients.claim())
  )
})

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url)
  const meme = url.origin === self.location.origin
  // Données scolaires et fichiers : toujours depuis le réseau.
  if (event.request.method !== 'GET' || !meme || url.pathname.startsWith('/api/') || url.pathname.startsWith('/uploads/')) {
    return
  }
  event.respondWith(
    fetch(event.request)
      .then((reponse) => {
        const copie = reponse.clone()
        caches.open(CACHE).then((cache) => cache.put(event.request, copie))
        return reponse
      })
      .catch(() => caches.match(event.request).then((trouve) => trouve || caches.match('/')))
  )
})
