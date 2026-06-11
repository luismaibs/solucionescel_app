const CACHE_NAME = 'solucionescel-v7';
const urlsToCache = [
  '/offline.html',
  '/assets/logo.svg',
  // CSS — todos los estilos locales
  '/assets/css/app.css',
  '/assets/css/panel.css',
  '/assets/css/inventario.css',
  '/assets/css/asistente-ia.css',
  '/assets/css/analiticas.css',
  '/assets/css/soporte.css',
  '/assets/css/estados.css',
  '/assets/css/notificaciones.css',
  // JS — todos los scripts locales
  '/assets/js/utils.js',
  '/assets/js/supabase-client.js',
  '/assets/js/bottom-sheet.js',
  '/assets/js/spa-router.js',
  '/assets/js/panel-utils.js',
  '/assets/js/panel-offcanvas.js',
  '/assets/js/panel-reparaciones.js',
  '/assets/js/panel.js',
  '/assets/js/realtime.js',
  '/assets/js/inventario.js',
  '/assets/js/asistente-ia.js',
  '/assets/js/estados.js',
  '/assets/js/notificaciones.js',
  // CDN críticos
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css',
];

// Install Event - Cache assets
self.addEventListener('install', (event) => {
  console.log('[Service Worker] Installing...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('[Service Worker] Caching app shell');
        return cache.addAll(urlsToCache);
      })
      .catch((err) => {
        console.log('[Service Worker] Cache failed:', err);
      })
  );
  self.skipWaiting();
});

// Activate Event - Clean up old caches
self.addEventListener('activate', (event) => {
  console.log('[Service Worker] Activating...');
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.log('[Service Worker] Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  return self.clients.claim();
});

// Fetch Event - Stale-while-revalidate para estáticos, Network-first para navegación
self.addEventListener('fetch', (event) => {
  // Skip non-GET requests
  if (event.request.method !== 'GET') return;

  // Skip chrome extensions
  if (event.request.url.startsWith('chrome-extension://')) return;

  // No cachear respuestas de API (evita datos obsoletos offline)
  try {
    const url = new URL(event.request.url);
    if (url.pathname.indexOf('/api/') !== -1) return;
  } catch (_) {}

  // Navegación (HTML): Network-first, fallback a cache y offline.html
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          if (response.status === 200) {
            const responseToCache = response.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(event.request, responseToCache);
            });
          }
          return response;
        })
        .catch(() => {
          return caches.match(event.request).then((cachedResponse) => {
            if (cachedResponse) {
              return cachedResponse;
            }
            return caches.match('/offline.html').then((offlineResponse) => {
              return offlineResponse || Response.error();
            });
          });
        })
    );
    return;
  }

  // Assets estáticos (CSS, JS, imágenes, fuentes): stale-while-revalidate
  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      const fetchPromise = fetch(event.request).then((networkResponse) => {
        if (networkResponse && networkResponse.status === 200) {
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseToCache);
          });
        }
        return networkResponse;
      }).catch(() => cachedResponse || Response.error());

      return cachedResponse || fetchPromise;
    })
  );
});

// Messages from clients
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
