// Service worker giełdy: aplikacja działa jak zainstalowana (PWA).
// Strategia: API zawsze z sieci (dane muszą być świeże), pliki aplikacji
// z sieci z zapasem w cache, żeby po utracie zasięgu otwierała się powłoka.
// Ścieżki liczone od katalogu sw.js — działa i pod /, i pod /gielda/.
const CACHE = 'gielda-v2';
const ROOT = new URL('./', self.location).pathname;

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll([ROOT, ROOT + 'manifest.webmanifest', ROOT + 'icon-192.png'])));
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))),
  );
  self.clients.claim();
});

self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  if (e.request.method !== 'GET' || url.origin !== location.origin || url.pathname.startsWith(ROOT + 'api/')) return;

  e.respondWith(
    fetch(e.request)
      .then((res) => {
        if (res.ok && (url.pathname.startsWith(ROOT + 'assets/') || url.pathname.startsWith(ROOT + 'uploads/'))) {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(e.request, copy));
        }
        return res;
      })
      .catch(() =>
        caches.match(e.request).then((hit) => hit || (e.request.mode === 'navigate' ? caches.match(ROOT) : undefined)),
      ),
  );
});
