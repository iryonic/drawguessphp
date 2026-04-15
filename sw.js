const CACHE_NAME = 'draw-guess-v18_STABLE';
const ASSETS = [
    './',
    'manifest.json',
    'assets/pwa/icon-512.png'
];

// 1. Install - Pre-cache core assets
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(async cache => {
            console.log('SW: Installing and pre-caching...');
            for (const url of ASSETS) {
                try {
                    const response = await fetch(url, { redirect: 'follow' });
                    if (response.ok) {
                        await cache.put(url, response);
                    }
                } catch (err) {
                    console.warn('SW: Failed to cache:', url);
                }
            }
        })
    );
    self.skipWaiting();
});

// 2. Activate - Cleanup old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(keys.map(key => {
                if (key !== CACHE_NAME) return caches.delete(key);
            }));
        })
    );
    self.clients.claim();
});

// 3. Fetch Strategy: Stale-While-Revalidate for Assets, Network-Only for API
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // ONLY cache http/https requests (fixes chrome-extension errors)
    if (!url.protocol.startsWith('http')) return;

    // Bypass API calls - should always be live
    if (url.pathname.includes('/api/') || event.request.method !== 'GET') {
        return;
    }

    event.respondWith(
        caches.match(event.request).then(cachedResponse => {
            const fetchPromise = fetch(event.request).then(networkResponse => {
                // Only cache valid successful responses
                if (networkResponse && networkResponse.status === 200) {
                    const responseClone = networkResponse.clone();
                    caches.open(CACHE_NAME).then(cache => {
                        cache.put(event.request, responseClone);
                    });
                }
                return networkResponse;
            }).catch(() => cachedResponse);

            return cachedResponse || fetchPromise;
        })
    );
});
