const CACHE_NAME = 'draw-guess-v1';
const ASSETS = [
    './',
    'manifest.json',
    'assets/pwa/icon-512.png'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(ASSETS))
    );
});

self.addEventListener('fetch', event => {
    // Skip API calls and non-GET requests
    if (event.request.url.includes('/api/') || event.request.method !== 'GET') {
        return;
    }

    event.respondWith(
        fetch(event.request).catch(async () => {
            const cache = await caches.open(CACHE_NAME);
            const cachedResponse = await cache.match(event.request);
            if (cachedResponse) {
                return cachedResponse;
            }
            // If both network and cache fail, throw error so it bubbles up correctly
            throw new Error('Not found in network or cache');
        })
    );
});
