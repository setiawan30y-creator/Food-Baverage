# Changelog

## 1.3.0 — 2026-10-22
- Keuangan → **Saldo Kas & Rekening** (`/keuangan/rekening`): rekening bank, QRIS, e-wallet & kas dengan saldo awal, penjualan masuk per rekening, mutasi manual (biaya admin, penarikan, setoran, koreksi), pindah dana antar rekening, mutasi dengan saldo berjalan, ekspor CSV.
- Pembayaran pesanan otomatis masuk ke rekening metode tersebut; bila satu metode punya beberapa rekening, kasir memilih rekening tujuan.
- **Riwayat Penjualan** (`/riwayat-penjualan`): pembayaran lunas per tanggal, metode & rekening, ringkasan & ekspor CSV.
- Menu **Saldo Awal Stok** di navigasi.
- Tombol tampilkan/sembunyikan kata sandi; CSS/JS kini selalu termuat versi terbaru (cache-busting otomatis).
- Migrasi baru: php artisan migrate. @2026-Alifiandra

## 1.2.0 — 2026-10-22
- SaaS: paket Basic/Pro/Enterprise dengan modul & batas; menu di luar paket tersembunyi & terkunci di server.
- Panel Super Admin `/admin`: dasbor platform, kelola penyewa, tagihan & verifikasi bukti bayar, pengaturan platform, "Masuk sebagai" pemilik (teraudit).
- Pendaftaran mandiri `/daftar` dengan trial & opsi contoh menu; langkah awal di Dasbor.
- Halaman Langganan untuk pemilik: tagihan, unggah bukti transfer, perpanjangan otomatis setelah diverifikasi; masa tenggang & penguncian tanpa menghapus data.
- Perintah `saas:superadmin`, `saas:check-subscriptions` (terjadwal harian).
- PWA: manifest dinamis per usaha (nama, warna, ikon dari logo), PWA pelanggan per usaha, pintasan, ikon maskable, halaman offline, tombol pasang & petunjuk iPhone.
- Migrasi baru: php artisan migrate. @2026-Alifiandra

## 1.1.0 — 2026-10-22
- Sisa porsi menu otomatis dari stok bahan × resep (dikurangi pesanan yang belum dibayar), tampil sebagai badge "Sisa N" di kasir, kolom "Sisa porsi" di halaman Menu (beserta bahan pembatasnya), dan kartu "Menu hampir habis" di Dasbor.
- Menu habis otomatis gelap & tidak bisa dipilih di kasir maupun menu pelanggan (QR / Order Ahead); server menolak pesanan yang melebihi stok, termasuk bahan yang dipakai bersama beberapa menu dalam satu keranjang.
- Stok porsi harian opsional per menu per cabang.
- Saklar per cabang "Kunci menu saat stok habis" (default aktif).
- Migrasi baru: php artisan migrate. @2026-Alifiandra

## 1.0.3 — 2026-10-22
- Perataan tabel terpusat di CSS: judul kolom selalu sejajar dengan isinya (kolom pertama & teks panjang rata kiri, lainnya rata tengah).
- Payroll: baris edit kini memakai sel per kolom (sebelumnya satu sel gabungan sehingga nilai bergeser dari judulnya); kolom Aksi diberi judul.
- Saldo awal: nilai yang sudah final tampil di kolom Jumlah & Harga masing-masing.
- Tabel berkolom banyak dibuat ringkas agar tidak terpotong; judul kolom boleh turun baris; kotak isian teks tanpa tipe kini bergaya normal.
- Tes otomatis TableLayoutTest memeriksa semua tabel di semua halaman (jumlah & perataan kolom). @2026-Alifiandra

## 1.0.2 — 2026-10-22
- Ilustrasi foto untuk 17 menu snack contoh (WebP 800×600), otomatis dipasang oleh SnackMenuSeeder pada menu yang belum punya foto. Generator di tools/menu-art. @2026-Alifiandra

## 1.0.1 — 2026-10-22
- Data contoh POS diganti menjadi kedai snack: kentang crispy, kentang spiral, corndog, chicken popcorn, churros, sosis bakar, paket hemat & minuman (SnackMenuSeeder, aman dijalankan ulang). @2026-Alifiandra

## 1.0.0 — 2026-10-22
- Rilis awal ALMARA Restaurant & Beverage OS (warung makan) sesuai blueprint v1.1. @2026-Alifiandra
