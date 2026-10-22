# ALMARA Restaurant & Beverage OS

Aplikasi SaaS multi-tenant untuk warung makan & usaha makanan/minuman, dibangun mengacu pada
[`docs/BLUEPRINT.md`](docs/BLUEPRINT.md) (v1.1).

> Laravel 13 · PHP 8.3+ · Blade + Alpine.js · MySQL · PWA — **@2026-Alifiandra**

## Fitur yang sudah tersedia (versi 1.0.0)

| Modul | Isi |
|---|---|
| Tenant & cabang | Isolasi data per tenant (global scope + middleware sebelum route binding), multi-cabang, pindah cabang, batas paket (cabang/pengguna), status langganan |
| Pengguna & peran | Pemilik, Manajer, Admin, Kasir, Dapur/Bar, Gudang, Staf — hak akses diperiksa di server (`ability:*`) |
| Profil & tema | Profil usaha + upload logo (preview, validasi), tema tenant (preset, HEX, font, cek kontras), mode terang/gelap per pengguna, widget cuaca & lokasi cabang |
| Menu | Kategori, foto, modifier (level pedas, topping, gula…), harga & ketersediaan per cabang, tandai habis, jadwal hari/jam, riwayat harga, harga hanya boleh diubah peran berwenang |
| POS | Dine-in/bungkus, meja, catatan item, diskon beralasan (di atas batas butuh manajer), pajak & service per cabang, idempotency (tidak dobel), pembayaran tunai/QRIS/transfer/e-wallet dengan referensi, nota 58/80 mm, cetak ulang teraudit |
| QR meja | QR unik per meja, cetak A4, buat ulang (QR lama mati), sesi meja, pindah meja, tutup sesi |
| Order Ahead | Guest checkout, pilih cabang, slot ambil sesuai jam buka & kapasitas, kode ambil 6 digit diverifikasi staf, halaman lacak pesanan, nota digital |
| Dapur (KDS) | Antrean real-time per stasiun, status item, timer & peringatan terlambat, bunyi pesanan baru, "siap" dikonfirmasi staf |
| Resep & HPP | Resep menu & **sub-recipe** (saus, sambal, bumbu, nasi, adonan…), versi resep, HPP tanpa hitung ganda, margin |
| Produksi | Batch sub-recipe, konsumsi aktual, yield aktual, selisih wajib beralasan, HPP batch |
| Persediaan | Ledger `stock_movements`, kartu stok, konversi kemasan, HPP rata-rata tertimbang, saldo awal, penyesuaian beralasan, waste dengan persetujuan berbasis nilai, stock opname |
| Pembelian | Supplier, pembelian → terima barang → utang & pembayaran, riwayat harga |
| Shift & closing | Buka shift, kas masuk/keluar/setoran, closing total atau per pecahan, selisih wajib beralasan, persetujuan, **History Closing** (filter, detail, transaksi terkait, buka ulang beraudit, cetak/PDF, ekspor CSV) |
| Karyawan & jadwal | Data pegawai terpisah dari akun, kasbon, jadwal mingguan P/S/M/OFF/C/I/L, rekap staf per hari, **papan shift A4 landscape** |
| Absensi GPS | Check-in/out dengan lokasi saat tombol ditekan saja, radius cabang, waktu server, tanda terlambat/pulang awal/di luar radius, koreksi manual beraudit, ekspor |
| Izin & lembur | Izin, sakit, cuti, lembur dengan persetujuan |
| Payroll | Bulanan, harian, per shift, per jam, komisi, borongan; tunjangan, lembur disetujui, bonus/THR, potongan (BPJS/PPh21 diisi sesuai aturan berlaku — tidak di-hardcode), cicilan kasbon, kunci setelah disetujui, slip privat |
| Laporan | Penjualan harian, kanal, kasir, cabang (konsolidasi), rekonsiliasi pembayaran, menu & margin, pemakaian & kebocoran stok, waste — ekspor CSV |
| Audit | Log tindakan sensitif (diskon, void, refund, closing, stok, harga, payroll, dll.) |
| PWA | Manifest + service worker (hanya aset statis; transaksi selalu lewat server), indikator offline |

### Tahap berikutnya (belum dikerjakan)
Integrasi payment gateway (callback server-side), WhatsApp Gateway, jurnal akuntansi double-entry & laporan neraca/laba rugi,
split bill & gabung meja, transfer stok antar cabang, akun & loyalitas pelanggan (login), reservasi/delivery, Livewire untuk
komponen real-time, Redis/queue produksi, panel super-admin SaaS.

## Instalasi di Laragon (Windows, PowerShell)

```powershell
cd C:\laragon\www\Food-Baverage
composer install
Copy-Item .env.example .env
php artisan key:generate

# buat database MySQL "fnb_almara" (Laragon: root tanpa password)
$mysql = (Get-ChildItem "C:\laragon\bin\mysql\*\bin\mysql.exe" | Select-Object -First 1).FullName
& $mysql -u root -e "CREATE DATABASE IF NOT EXISTS fnb_almara CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan migrate --seed
php artisan serve
```

Buka `http://127.0.0.1:8000` (atau `http://food-baverage.test` lewat Laragon).
Tidak perlu `npm run build` — CSS/JS sudah siap di `public/`.

### Sisa porsi & menu habis
Sisa porsi tiap menu dihitung otomatis dari **stok bahan × resep** cabang aktif, dikurangi bahan untuk pesanan yang belum dibayar.
Menu yang habis otomatis gelap dan tidak bisa dipesan, baik di kasir maupun di menu pelanggan; server juga menolak pesanan yang melebihi stok.
Isi **Stok harian** di halaman Menu bila menu dibatasi sekian porsi per hari. Penguncian bisa dimatikan per cabang
(Pengaturan → Cabang → *Kunci menu saat stok habis*), misalnya bila pencatatan stok bahan belum rapi.
Menu tanpa resep tidak dilacak dan selalu tersedia.

### Data contoh: kedai snack
`php artisan migrate --seed` mengisi contoh **Kedai Snack Contoh** dengan 17 menu dalam 5 kategori:
Kentang (Crispy Original/Keju/Balado, Kentang Spiral, Spiral Keju Lumer), Corndog (Sosis, Mozzarella, Mix, Kentang),
Camilan Lain (Chicken Popcorn, Sosis Bakar Jumbo, Churros), Paket Hemat, dan Minuman. Data ini juga mencakup pilihan tambahan
(ukuran, saus, bumbu, level pedas, gula), 31 bahan baku dengan konversi kemasan, 5 racikan (adonan corndog, saus keju, bumbu tabur keju,
gula kayu manis, adonan churros) beserta batch produksinya, resep tiap menu, dan HPP/margin.
Setiap menu juga diberi ilustrasi foto (`database/seeders/images/menus`, dibuat dengan `tools/menu-art`); foto hanya dipasang pada menu yang belum punya foto.

Untuk menambahkan menu snack ke database yang sudah berisi data (tanpa menghapus data; aman dijalankan ulang):

```powershell
php artisan db:seed --class=SnackMenuSeeder
```

Untuk mengulang dari nol (**menghapus semua data**):

```powershell
php artisan migrate:fresh --seed
```

### Akun contoh (kata sandi: `password`)

| Peran | Email |
|---|---|
| Pemilik | owner@contoh.test |
| Manajer | manajer@contoh.test |
| Kasir | kasir@contoh.test |
| Dapur | dapur@contoh.test |
| Gudang | gudang@contoh.test |

Ganti kata sandi semua akun sebelum dipakai sungguhan.

Tautan pelanggan: Order Ahead `/o/kedai-snack`, QR meja dari menu **Meja & QR**.

### Menjalankan tes

```powershell
php artisan test
```

## Catatan produksi
- Wajib HTTPS (absensi GPS & PWA memerlukan konteks aman).
- Ubuntu 24.04 + Nginx + PHP-FPM + MySQL + Redis; jalankan `php artisan queue:work` via Supervisor dan `schedule:run` via cron.
- Backup database & `storage/app` terjadwal, uji restore berkala.
- Aturan pajak, BPJS, PPh 21, THR, upah minimum harus diverifikasi dengan regulasi terbaru.

---
@2026-Alifiandra
