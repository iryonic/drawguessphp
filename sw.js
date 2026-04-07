const CACHE_NAME = 'draw-guess-v12_NUCLEAR';
const ASSETS = [
    './',
    'manifest.json',
    'assets/pwa/icon-512.png'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(async cache => {
            console.log('SW: Pre-caching assets');
            for (const url of ASSETS) {
                try {
                    const response = await fetch(url, { redirect: 'follow' });
                    if (!response.ok) throw new Error(`Fetch failed for ${url} with status ${response.status}`);
                    await cache.put(url, response);
                } catch (err) {
                    console.error('SW: Failed to cache:', url, err);
                }
            }
        })
    );
    self.skipWaiting();
});

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

self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET' || 
        event.request.url.includes('/api/') || 
        !event.request.url.startsWith('http')) {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then(response => {
                if (response.status === 200) {
                    const resClone = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, resClone));
                }
                return response;
            })
            .catch(() => caches.match(event.request))
    );
});
