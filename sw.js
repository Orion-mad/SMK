// sw.js
const CACHE = 'lcars-cache-v1.1';
const ASSETS = [
  './',
  './index.html',
  './assets/css/lcars.css',
  './assets/js/app.js',
  './assets/js/router.js',
  './manifest.json',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css'
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(ASSETS)));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then(keys => Promise.all(
    keys.filter(k => k !== CACHE).map(k => caches.delete(k))
  )));
});

self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  if(url.pathname.startsWith('/api')) return; // no cache para API
  e.respondWith(
    caches.match(e.request).then(res => res ||
      fetch(e.request).then(r => {
        const copy = r.clone();
        caches.open(CACHE).then(c => c.put(e.request, copy));
        return r;
      }).catch(() => caches.match('./index.html'))
    )
  );
});
