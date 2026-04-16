const CACHE_NAME = 'draw-guess-v19_STABLE';
const STATIC_ASSETS = [
    'manifest.json',
    'assets/pwa/icon-512.png'
];

// File types that should NEVER be served from cache (always live)
// This prevents stale JS/PHP from breaking the game after updates
const NEVER_CACHE = ['.js', '.php'];

function shouldNeverCache(url) {
    return NEVER_CACHE.some(ext => url.pathname.endsWith(ext));
}

// 1. Install - Pre-cache only static assets (icons, manifest)
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(async cache => {
            console.log('SW v19: Installing...');
            for (const url of STATIC_ASSETS) {
                try {
                    const response = await fetch(url, { redirect: 'follow' });
                    if (response.ok) await cache.put(url, response);
                } catch (err) {
                    console.warn('SW: Failed to cache:', url);
                }
            }
        })
    );
    self.skipWaiting();
});

// 2. Activate - Cleanup old caches immediately
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(keys.map(key => {
                if (key !== CACHE_NAME) {
                    console.log('SW: Deleting old cache:', key);
                    return caches.delete(key);
                }
            }));
        })
    );
    self.clients.claim();
});

// 3. Fetch Strategy
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Only handle http/https (ignore chrome-extension etc.)
    if (!url.protocol.startsWith('http')) return;

    // NETWORK-ONLY: API calls and all POST requests
    if (url.pathname.includes('/api/') || event.request.method !== 'GET') {
        return;
    }

    // NETWORK-FIRST: JS and PHP files — always fresh, fallback to cache if offline
    // This is the key fix: prevents stale game.js from being served after updates
    if (shouldNeverCache(url)) {
        event.respondWith(
            fetch(event.request)
                .then(networkResponse => {
                    // Update the cache with the fresh response for offline use
                    if (networkResponse && networkResponse.status === 200) {
                        const clone = networkResponse.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
                    }
                    return networkResponse;
                })
                .catch(() => caches.match(event.request)) // Offline fallback
        );
        return;
    }

    // CACHE-FIRST: Static assets (images, fonts, manifest)
    // Serve from cache instantly, update in background (stale-while-revalidate)
    event.respondWith(
        caches.match(event.request).then(cachedResponse => {
            const fetchPromise = fetch(event.request).then(networkResponse => {
                if (networkResponse && networkResponse.status === 200) {
                    const clone = networkResponse.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
                }
                return networkResponse;
            }).catch(() => cachedResponse);

            return cachedResponse || fetchPromise;
        })
    );
});
