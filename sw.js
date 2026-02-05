const CACHE_NAME = 'draw-guess-v1';
const ASSETS = [
    './',
    'index.php',
    'manifest.json',
    'assets/pwa/icon-512.png'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(ASSETS))
    );
});

self.addEventListener('fetch', event => {
    event.respondWith(
        fetch(event.request).catch(() => caches.match(event.request))
    );
});
