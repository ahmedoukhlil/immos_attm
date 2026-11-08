/**
 * Service Worker pour l'application PWA de scan d'inventaire
 * Gère uniquement le cache applicatif. La reprise des opérations est pilotée
 * au premier plan afin de ne jamais stocker de jeton d'accès dans IndexedDB.
 */

const CACHE_NAME = 'inventaire-scanner-v30';
const ASSETS_TO_CACHE = [
  './index-v2.html',
  './app-v2.js',
  './styles.css',
  './manifest.json',
  '../images/icons/icon-192x192.png',
  '../images/icons/icon-512x512.png'
].map((path) => new URL(path, self.registration.scope).href);

/**
 * Installation : mise en cache des assets essentiels
 */
self.addEventListener('install', (event) => {
  console.log('[Service Worker] Installation...');
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('[Service Worker] Mise en cache des assets');
        return cache.addAll(ASSETS_TO_CACHE);
      })
      .catch((error) => {
        console.error('[Service Worker] Erreur lors de la mise en cache:', error);
      })
      .then(() => {
        // Forcer l'activation immédiate du nouveau service worker
        return self.skipWaiting();
      })
  );
});

/**
 * Activation : nettoyage des anciens caches
 */
self.addEventListener('activate', (event) => {
  console.log('[Service Worker] Activation...');
  
  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => {
            if (cacheName.startsWith('inventaire-scanner-') && cacheName !== CACHE_NAME) {
              console.log('[Service Worker] Suppression ancien cache:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => {
        // Prendre le contrôle de toutes les pages immédiatement
        return self.clients.claim();
      })
      .catch((error) => {
        console.error('[Service Worker] Erreur lors de l\'activation:', error);
      })
  );
});

/**
 * Fetch : stratégie de cache selon le type de ressource
 * - Cache First pour les assets statiques (rapide)
 * - Network First pour les appels API (données à jour)
 */
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Ignorer les requêtes non-GET
  if (request.method !== 'GET') {
    return;
  }

  // Les réponses authentifiées ne doivent jamais être placées dans un cache partagé.
  if (url.pathname.includes('/api/')) {
    return;
  }

  const shouldRefreshFromNetwork = request.mode === 'navigate'
    || request.destination === 'script'
    || request.destination === 'style';

  if (shouldRefreshFromNetwork) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (response && response.status === 200 && response.type === 'basic') {
            const responseClone = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, responseClone));
          }

          return response;
        })
        .catch(async () => {
          return await caches.match(request)
            || new Response('Ressource non disponible hors ligne', {
              status: 503,
              statusText: 'Service Unavailable'
            });
        })
    );

    return;
  }

  // Stratégie Cache First pour les images et autres assets immuables.
  event.respondWith(
    caches.match(request)
      .then((cachedResponse) => {
        if (cachedResponse) {
          console.log('[Service Worker] Ressource depuis le cache:', request.url);
          return cachedResponse;
        }

        // Si pas dans le cache, récupérer depuis le réseau
        return fetch(request)
          .then((response) => {
            // Vérifier que la réponse est valide
            if (!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }

            // Mettre en cache les nouvelles ressources
            const responseClone = response.clone();
            
            caches.open(CACHE_NAME)
              .then((cache) => {
                cache.put(request, responseClone);
              })
              .catch((error) => {
                console.error('[Service Worker] Erreur mise en cache asset:', error);
              });

            return response;
          })
          .catch((error) => {
            console.error('[Service Worker] Erreur fetch:', error);
            // Retourner une réponse d'erreur si le fetch échoue
            return new Response('Ressource non disponible', {
              status: 404,
              statusText: 'Not Found'
            });
          });
      })
  );
});

/**
 * Gestion des messages depuis l'application principale
 * Permet la communication bidirectionnelle entre l'app et le service worker
 */
self.addEventListener('message', (event) => {
  console.log('[Service Worker] Message reçu:', event.data);

  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }

});
