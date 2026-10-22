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

/* ---------- PWA: tombol "Pasang aplikasi" & petunjuk iPhone/iPad ---------- */
(function () {
  var deferred = null;
  function toggle(sel, show) { document.querySelectorAll(sel).forEach(function (el) { el.hidden = !show; }); }
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferred = e;
    toggle('[data-pwa-install]', true);
  });
  window.addEventListener('appinstalled', function () { deferred = null; toggle('[data-pwa-install]', false); });
  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('[data-pwa-install]');
    if (btn && deferred) {
      deferred.prompt();
      deferred.userChoice.finally(function () { deferred = null; toggle('[data-pwa-install]', false); });
    }
    var close = e.target.closest && e.target.closest('[data-pwa-ios-close]');
    if (close) {
      toggle('[data-pwa-ios]', false);
      try { localStorage.setItem('pwa-ios-hint', '1'); } catch (x) {}
    }
  });
  document.addEventListener('DOMContentLoaded', function () {
    var ios = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var standalone = window.navigator.standalone || window.matchMedia('(display-mode: standalone)').matches;
    var dismissed = false;
    try { dismissed = localStorage.getItem('pwa-ios-hint') === '1'; } catch (x) {}
    if (ios && !standalone && !dismissed) toggle('[data-pwa-ios]', true);
  });
})();

/* ---------- Tombol tampilkan/sembunyikan kata sandi (otomatis di semua kolom kata sandi) — @2026-Alifiandra ---------- */
(function () {
  var eye = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';
  var eyeOff = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 5.1A10.6 10.6 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4.2M6.6 6.6A17 17 0 0 0 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>';
  function enhance(input) {
    if (input.dataset.pwToggle) return;
    input.dataset.pwToggle = '1';
    var wrap = document.createElement('div');
    wrap.className = 'pw-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pw-toggle';
    btn.setAttribute('aria-label', 'Tampilkan kata sandi');
    btn.title = 'Tampilkan kata sandi';
    btn.innerHTML = eye;
    btn.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.innerHTML = show ? eyeOff : eye;
      var label = show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi';
      btn.setAttribute('aria-label', label);
      btn.title = label;
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      input.focus();
    });
    wrap.appendChild(btn);
    // kembalikan ke tersembunyi sebelum formulir dikirim (agar tidak tersimpan sebagai teks oleh browser)
    if (input.form) input.form.addEventListener('submit', function () { input.type = 'password'; });
  }
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[type=password]').forEach(enhance);
  });
})();
