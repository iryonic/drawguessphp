const CACHE_NAME = 'draw-guess-v1';
const ASSETS = [
    '/drawguess/',
    '/drawguess/index.php',
    '/drawguess/manifest.json',
    '/drawguess/assets/pwa/icon-512.png'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(ASSETS))
    );
});

self.addEventListener('fetch', event => {
    // Strategy: Network first, then cache
    event.respondWith(
        fetch(event.request).catch(() => caches.match(event.request))
    );
});
