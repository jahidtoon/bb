// AI Generator Service Worker
const CACHE_NAME = 'ai-generator-v1';

// Assets to cache on install
const PRECACHE_ASSETS = [
  './index.php',
  './css/main.css',
  './images/background.jpg',
  './offline.html',
  './user/login.php',
  './user/register.php',
  './manifest.json'
];

// Install event
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache');
        return cache.addAll(PRECACHE_ASSETS);
      })
      .then(() => self.skipWaiting())
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  const currentCaches = [CACHE_NAME];
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return cacheNames.filter(cacheName => !currentCaches.includes(cacheName));
    }).then(cachesToDelete => {
      return Promise.all(cachesToDelete.map(cacheToDelete => {
        return caches.delete(cacheToDelete);
      }));
    }).then(() => self.clients.claim())
  );
});

// Network first, falling back to cache strategy
self.addEventListener('fetch', event => {
  // Skip cross-origin requests
  if (!event.request.url.startsWith(self.location.origin)) {
    return;
  }

  // Skip non-GET requests
  if (event.request.method !== 'GET') {
    return;
  }

  // For API and generate requests, always go to network
  if (event.request.url.includes('/api.php') || 
      event.request.url.includes('/generate.php')) {
    return;
  }

  // For HTML pages, use network first, fall back to cache
  if (event.request.headers.get('accept').includes('text/html')) {
    event.respondWith(
      fetch(event.request)
        .then(response => {
          // If the response was good, clone it and store it in the cache
          if (response.status === 200) {
            const clonedResponse = response.clone();
            caches.open(CACHE_NAME).then(cache => {
              cache.put(event.request, clonedResponse);
            });
          }
          return response;
        })
        .catch(() => {
          // Network failed, try the cache
          return caches.match(event.request)
            .then(cachedResponse => {
              if (cachedResponse) {
                return cachedResponse;
              }
              // If both network and cache fail, show fallback page
              return caches.match('./offline.html');
            });
        })
    );
    return;
  }

  // For all other requests (CSS, images, etc.), try the cache first, fall back to network
  event.respondWith(
    caches.match(event.request)
      .then(cachedResponse => {
        if (cachedResponse) {
          return cachedResponse;
        }
        return fetch(event.request)
          .then(response => {
            // If the response was good, clone it and store it in the cache
            if (response.status === 200) {
              const clonedResponse = response.clone();
              caches.open(CACHE_NAME).then(cache => {
                cache.put(event.request, clonedResponse);
              });
            }
            return response;
          });
      })
  );
});

// Background sync for offline image generation requests
self.addEventListener('sync', event => {
  if (event.tag === 'sync-generations') {
    event.waitUntil(syncGenerations());
  }
});

// Function to sync pending generations
async function syncGenerations() {
  try {
    const pendingRequests = await getPendingRequests();
    
    for (const request of pendingRequests) {
      await fetch('/api.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(request)
      });
      
      await markRequestAsComplete(request.id);
    }
    
    // If we have successfully synced, show a notification
    if (pendingRequests.length > 0) {
      self.registration.showNotification('ByteBrain AI Generator', {
        body: `${pendingRequests.length} generations completed while you were offline!`,
        icon: './images/icon-192x192.png'
      });
    }
  } catch (error) {
    console.error('Sync failed:', error);
  }
}

// Placeholder functions for storage
// In a real app, you would implement these with IndexedDB
function getPendingRequests() {
  // This would get pending requests from IndexedDB
  return [];
}

function markRequestAsComplete(id) {
  // This would mark a request as complete in IndexedDB
  return Promise.resolve();
}

// Handle push notifications
self.addEventListener('push', event => {
  const data = event.data.json();
  const options = {
    body: data.body,
    icon: './images/icon-192x192.png',
    badge: './images/icon-192x192.png',
    data: {
      url: data.url
    }
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// Handle notification click
self.addEventListener('notificationclick', event => {
  event.notification.close();
  
  if (event.notification.data && event.notification.data.url) {
    event.waitUntil(
      clients.openWindow(event.notification.data.url)
    );
  }
}); 