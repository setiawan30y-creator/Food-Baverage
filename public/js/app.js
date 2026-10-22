/* ALMARA F&B OS — helper front-end. @2026-Alifiandra */
window.rupiah = function (n) {
  return 'Rp ' + Math.round(Number(n) || 0).toLocaleString('id-ID');
};
window.uuid = function () {
  if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
  return 'k' + Date.now().toString(36) + Math.random().toString(36).slice(2);
};
/* Kirim JSON ke server. Status sukses HANYA dari respons server. */
window.api = async function (url, data, method) {
  if (!navigator.onLine) throw new Error('Tidak ada koneksi internet. Coba lagi setelah koneksi pulih.');
  const token = document.querySelector('meta[name="csrf-token"]')?.content;
  const res = await fetch(url, {
    method: method || 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
    body: data ? JSON.stringify(data) : undefined,
    credentials: 'same-origin',
  });
  let body = {};
  try { body = await res.json(); } catch (e) {}
  if (!res.ok) {
    let msg = body.message || ('Gagal (' + res.status + ')');
    if (body.errors) msg = Object.values(body.errors).flat()[0] || msg;
    if (res.status === 419) msg = 'Sesi kedaluwarsa. Muat ulang halaman.';
    throw new Error(msg);
  }
  return body;
};
(function () {
  function net() {
    var el = document.getElementById('net-status');
    if (el) el.style.display = navigator.onLine ? 'none' : 'block';
  }
  window.addEventListener('online', net);
  window.addEventListener('offline', net);
  document.addEventListener('DOMContentLoaded', net);
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); });
  }
})();
