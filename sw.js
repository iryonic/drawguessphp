/**
 * DrawGuess PWA Service Worker
 * Enhanced caching strategy with automatic updates.
 */

const CACHE_VERSION = 'v' + new Date().getTime(); // Unique version on every SW change
const CACHE_NAME = 'draw-guess-' + CACHE_VERSION;

const STATIC_ASSETS = [
    './',
    'manifest.json',
    'assets/pwa/icon-512.png'
];

// 1. Install - Pre-cache critical assets
self.addEventListener('install', event => {
    self.skipWaiting(); // Force the waiting service worker to become the active service worker
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(STATIC_ASSETS);
        })
    );
});

// 2. Activate - Cleanup old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(
                keys.map(key => {
                    if (key !== CACHE_NAME) {
                        return caches.delete(key);
                    }
                })
            );
        }).then(() => {
            return self.clients.claim(); // Take control of all pages immediately
        })
    );
});

// 3. Fetch Strategy: Network First for PHP/API, Stale-While-Revalidate for Assets
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Only handle GET requests and http/https protocols
    if (event.request.method !== 'GET' || !url.protocol.startsWith('http')) {
        return;
    }

    // NETWORK ONLY: API and Admin calls
    if (url.pathname.includes('/api/') || url.pathname.includes('/admin/')) {
        return;
    }

    // NETWORK FIRST: PHP pages (always fresh)
    if (url.pathname.endsWith('.php') || url.pathname.endsWith('/')) {
        event.respondWith(
            fetch(event.request)
                .catch(() => caches.match(event.request))
        );
        return;
    }

    // STALE-WHILE-REVALIDATE: CSS, JS, Images
    // Note: Since we use PHP versioning (filemtime), the URL will change when the file updates.
    // This naturally bypasses the cache for updated files.
    event.respondWith(
        caches.open(CACHE_NAME).then(cache => {
            return cache.match(event.request).then(response => {
                const fetchPromise = fetch(event.request).then(networkResponse => {
                    if (networkResponse.status === 200) {
                        cache.put(event.request, networkResponse.clone());
                    }
                    return networkResponse;
                });
                return response || fetchPromise;
            });
        })
    );
});
