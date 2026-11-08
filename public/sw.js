// Service Worker pour Inventaire Pro
const CACHE_NAME = 'inventaire-pro-v2';
const RUNTIME_CACHE = 'inventaire-pro-runtime-v2';

// Fichiers à mettre en cache lors de l'installation
// Note: Les fichiers CSS/JS compilés par Vite sont gérés dynamiquement
const PRECACHE_URLS = [
  '/images/icons/icon-192x192.png',
  '/images/icons/icon-512x512.png',
  '/manifest.json'
];

// Installation du Service Worker
self.addEventListener('install', (event) => {
  console.log('[Service Worker] Installation...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('[Service Worker] Mise en cache des fichiers statiques');
        // Utiliser Promise.all avec cache.add() pour gérer les erreurs individuellement
        return Promise.allSettled(
          PRECACHE_URLS.map((url) => {
            return cache.add(url).catch((error) => {
              console.warn(`[Service Worker] Impossible de mettre en cache ${url}:`, error);
              // Ne pas faire échouer l'installation si un fichier ne peut pas être mis en cache
              return null;
            });
          })
        );
      })
      .then(() => {
        console.log('[Service Worker] Installation terminée');
        return self.skipWaiting();
      })
      .catch((error) => {
        console.error('[Service Worker] Erreur lors de l\'installation:', error);
        // Même en cas d'erreur, on continue l'installation
        return self.skipWaiting();
      })
  );
});

// Activation du Service Worker
self.addEventListener('activate', (event) => {
  console.log('[Service Worker] Activation...');
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames
          .filter((cacheName) => {
            return cacheName.startsWith('inventaire-pro-')
              && cacheName !== CACHE_NAME
              && cacheName !== RUNTIME_CACHE;
          })
          .map((cacheName) => {
            console.log('[Service Worker] Suppression de l\'ancien cache:', cacheName);
            return caches.delete(cacheName);
          })
      );
    })
    .then(() => self.clients.claim())
  );
});

// Stratégie de mise en cache : Network First, puis Cache
self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  // Ignorer les requêtes non-GET
  if (request.method !== 'GET') {
    return;
  }

  // Ignorer les requêtes vers l'API (doivent toujours être en ligne)
  if (url.pathname.startsWith('/api/')) {
    return;
  }

  // Le scanner possède son propre service worker, son cache et sa stratégie
  // hors ligne. Le worker principal ne doit jamais intercepter ce périmètre.
  if (url.pathname.startsWith('/pwa/')) {
    return;
  }

  // Les navigations de l'application principale doivent toujours provenir
  // du serveur. Cela évite qu'une ancienne application partageant l'origine
  // localhost reste affichée après un redémarrage.
  if (request.mode === 'navigate') {
    return;
  }

  // Ignorer les requêtes cross-origin (domaines externes)
  // Ne mettre en cache que les ressources du même domaine
  if (url.origin !== location.origin) {
    // Pour les ressources externes, laisser le navigateur gérer normalement
    return;
  }

  // Ignorer les requêtes vers des CDN externes (fonts, chart.js, etc.)
  const externalDomains = [
    'fonts.bunny.net',
    'cdn.jsdelivr.net',
    'fonts.googleapis.com',
    'fonts.gstatic.com',
    'unpkg.com',
    'cdnjs.cloudflare.com'
  ];
  
  if (externalDomains.some(domain => url.hostname.includes(domain))) {
    return;
  }

  // Ignorer les routes de l'application principale (pas de la PWA)
  // Ces routes sont souvent redirigées et ne doivent pas être mises en cache
  const appRoutes = [
    '/dashboard',
    '/localisations',
    '/biens',
    '/inventaires',
    '/users',
    '/rapports',
    '/settings',
    '/login',
    '/logout'
  ];
  
  if (appRoutes.some(route => url.pathname.startsWith(route))) {
    // Laisser le navigateur gérer ces routes normalement (sans interception)
    return;
  }

  // Ignorer les requêtes avec des redirections (mode redirect)
  // Les redirections ne peuvent pas être mises en cache correctement
  if (request.redirect === 'follow' && url.search.includes('#')) {
    return;
  }

  event.respondWith(
    fetch(request, { redirect: 'follow' })
      .then((response) => {
        // Ignorer les réponses de redirection (3xx)
        if (response.redirected || response.status >= 300 && response.status < 400) {
          // Ne pas mettre en cache les redirections
          return response;
        }

        // Vérifier que la réponse est valide
        if (!response || response.status !== 200 || response.type === 'error') {
          // Si la réponse n'est pas valide, essayer le cache
          return caches.match(request).then((cachedResponse) => {
            if (cachedResponse) {
              return cachedResponse;
            }
            // Si pas de cache, retourner la réponse originale même si elle est en erreur
            return response;
          });
        }

        // Cloner la réponse car elle ne peut être utilisée qu'une fois
        const responseToCache = response.clone();

        // Mettre en cache les réponses réussies (uniquement pour le même domaine)
        // Ne pas mettre en cache les réponses HTML de l'application principale
        const isHTML = response.headers.get('content-type')?.includes('text/html');
        const appRoutesList = [
          '/dashboard',
          '/localisations',
          '/biens',
          '/inventaires',
          '/users',
          '/rapports',
          '/settings',
          '/login',
          '/logout'
        ];
        const isAppRoute = appRoutesList.some(route => url.pathname.startsWith(route));
        
        if (response.status === 200 && url.origin === location.origin && !isAppRoute) {
          // Ne mettre en cache que les assets statiques, pas les pages HTML de l'app
          if (!isHTML || url.pathname.startsWith('/pwa/')) {
            caches.open(RUNTIME_CACHE).then((cache) => {
              cache.put(request, responseToCache).catch((error) => {
                console.warn('[Service Worker] Erreur lors de la mise en cache:', error);
              });
            });
          }
        }

        return response;
      })
      .catch((error) => {
        console.warn('[Service Worker] Erreur de fetch pour', request.url, error);
        
        // Si le réseau échoue, essayer le cache
        return caches.match(request).then((cachedResponse) => {
          if (cachedResponse) {
            return cachedResponse;
          }

          // Si pas de cache et que c'est une navigation, retourner la page d'accueil
          if (request.mode === 'navigate') {
            return caches.match('/').then((homePage) => {
              if (homePage) {
                return homePage;
              }
              // Si même la page d'accueil n'est pas en cache, retourner une réponse d'erreur
              return new Response('Hors ligne', {
                status: 503,
                statusText: 'Service Unavailable',
                headers: new Headers({
                  'Content-Type': 'text/html'
                })
              });
            });
          }

          // Pour les autres requêtes, retourner une réponse d'erreur
          return new Response('Ressource non disponible hors ligne', {
            status: 503,
            statusText: 'Service Unavailable',
            headers: new Headers({
              'Content-Type': 'text/plain'
            })
          });
        });
      })
  );
});

// Gestion des messages depuis l'application
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

// Notification de mise à jour disponible
self.addEventListener('updatefound', () => {
  console.log('[Service Worker] Mise à jour disponible');
});

