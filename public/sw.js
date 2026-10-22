/*
 * Service worker ALMARA F&B OS — @2026-Alifiandra
 * - Aset statis (CSS/JS/ikon) di-cache agar aplikasi cepat dibuka & bisa dipasang.
 * - Halaman selalu diambil dari jaringan; bila offline tampil /offline.html.
 * - Transaksi (POST) tidak pernah di-cache: pesanan/pembayaran hanya sah bila server mengonfirmasi.
 */
const CACHE = 'almara-static-v2';
const ASSETS = ['/offline.html', '/css/app.css', '/js/app.js', '/icons/icon.svg', '/icons/icon-192.png', '/icons/icon-512.png', '/icons/icon-maskable-512.png'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ASSETS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;

  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
    return;
  }
  const isStatic = /^\/(css|js|icons)\//.test(url.pathname);
  if (!isStatic) return;
  e.respondWith(
    fetch(req).then((res) => {
      if (res.ok) {
        const copy = res.clone();
        caches.open(CACHE).then((c) => c.put(req, copy));
      }
      return res;
    }).catch(() => caches.match(req, { ignoreSearch: true }))
  );
});
