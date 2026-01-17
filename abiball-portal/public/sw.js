/**
 * Service Worker für Offline-Support
 * Cached Dashboard-Inhalte und PDFs im Hintergrund
 */

const CACHE_NAME = 'abiball-v2';
const RUNTIME_CACHE = 'abiball-runtime-v2';

// Assets die sofort gecacht werden sollen
const PRECACHE_ASSETS = [
  '/',
  '/dashboard.php',
  '/login.php',
  '/assets/css/style.css',
  '/assets/js/form-loading.js',
  '/assets/js/ui-enhancements.js',
  '/images/saal.jpeg',
  '/images/favicon.png',
  '/manifest.webmanifest'
];

// Install Event - Precache kritische Assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(PRECACHE_ASSETS))
      .then(() => self.skipWaiting())
  );
});

// Activate Event - Alte Caches löschen
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME && cacheName !== RUNTIME_CACHE) {
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch Event - Network First mit Cache Fallback
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip cross-origin requests
  if (url.origin !== location.origin) {
    return;
  }

  // Skip admin/door paths
  if (url.pathname.includes('admin') || url.pathname.includes('door')) {
    return;
  }

  // PDF-Caching Strategie (Cache dann Network)
  if (url.pathname.includes('/ticket/pdf.php')) {
    event.respondWith(
      caches.match(request).then((cachedResponse) => {
        if (cachedResponse) {
          return cachedResponse;
        }
        
        return fetch(request).then((response) => {
          // Nur erfolgreiche Responses cachen
          if (response.status === 200) {
            const responseToCache = response.clone();
            caches.open(RUNTIME_CACHE).then((cache) => {
              cache.put(request, responseToCache);
            });
          }
          return response;
        });
      })
    );
    return;
  }

  // Standard Strategie: Network First, dann Cache
  event.respondWith(
    fetch(request)
      .then((response) => {
        // Nur GET requests cachen
        if (request.method !== 'GET') {
          return response;
        }

        // Nur erfolgreiche Responses cachen
        if (response.status === 200) {
          const responseToCache = response.clone();
          
          caches.open(RUNTIME_CACHE).then((cache) => {
            cache.put(request, responseToCache);
          });
        }
        
        return response;
      })
      .catch(() => {
        // Wenn offline, versuche aus Cache zu laden
        return caches.match(request).then((cachedResponse) => {
          if (cachedResponse) {
            return cachedResponse;
          }
          
          // Fallback für Navigation (HTML Seiten)
          if (request.mode === 'navigate') {
            return caches.match('/dashboard.php');
          }
        });
      })
  );
});

// Message Event - PDF-Caching im Hintergrund (idle time)
self.addEventListener('message', (event) => {
  if (event.data.type === 'CACHE_PDF') {
    const pdfUrl = event.data.url;
    
    // Nur während idle time cachen (requestIdleCallback Simulation)
    setTimeout(() => {
      fetch(pdfUrl, { priority: 'low' })
        .then((response) => {
          if (response.status === 200) {
            return caches.open(RUNTIME_CACHE).then((cache) => {
              cache.put(pdfUrl, response);
            });
          }
        })
        .catch(() => {
          // Fehler beim Caching ignorieren (Best-Effort)
        });
    }, 2000); // 2 Sekunden Delay für niedrige Priorität
  }
  
  if (event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
