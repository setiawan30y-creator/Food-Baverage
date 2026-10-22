/*
 * Service worker ALMARA F&B OS — @2026-Alifiandra
 * Hanya meng-cache aset statis (CSS/JS/ikon) agar antarmuka cepat dimuat.
 * Halaman & transaksi SELALU lewat jaringan: pesanan/pembayaran tidak pernah
 * dianggap berhasil tanpa konfirmasi server.
 */
const CACHE = 'almara-static-v1';
const ASSETS = ['/css/app.css', '/js/app.js', '/icons/icon.svg', '/icons/icon-192.png', '/manifest.webmanifest'];

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
  const isStatic = /^\/(css|js|icons)\//.test(url.pathname) || url.pathname === '/manifest.webmanifest';
  if (!isStatic) return; // halaman dinamis: biarkan ke jaringan
  e.respondWith(
    fetch(req).then((res) => {
      const copy = res.clone();
      caches.open(CACHE).then((c) => c.put(req, copy));
      return res;
    }).catch(() => caches.match(req))
  );
});
