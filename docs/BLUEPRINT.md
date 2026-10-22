# ALMARA Restaurant & Beverage OS

## Blueprint Induk Sistem SaaS Multi-Tenant

**Status:** Blueprint konsolidasi berdasarkan keputusan yang telah
disetujui\
**Versi dokumen:** 1.1\
**Tanggal:** 21 Oktober 2026\
**Pemilik produk:** ALMARA\
**Tujuan:** Platform operasional restoran dan usaha makanan/minuman yang
dapat digunakan oleh banyak jenis usaha, tenant, dan cabang.

------------------------------------------------------------------------

## 1. Ringkasan Eksekutif

ALMARA Restaurant & Beverage OS adalah platform SaaS multi-tenant untuk
mengelola operasional usaha makanan dan minuman dari satu sistem
terpadu. Sistem ditujukan untuk warung nasi, kentang crispy dan street
food, kedai minuman, coffee shop, bakery, bakso, ayam/seafood, restoran,
food court, hingga grup restoran multi-cabang.

Platform mengintegrasikan pemesanan pelanggan, kasir/POS, QR menu dan QR
meja, dapur/Kitchen Display System (KDS), resep dan HPP, persediaan,
pembelian, produksi, keuangan, closing kasir, karyawan, jadwal shift,
absensi GPS, payroll, pelanggan, loyalitas, notifikasi, dan laporan.

### Prinsip utama

1.  **Satu platform, banyak tenant:** setiap usaha memiliki data,
    konfigurasi, pengguna, branding, dan cabang yang terisolasi.
2.  **Modular monolith terlebih dahulu:** domain sistem dipisah per
    modul, tetapi deployment awal tetap sederhana dan mudah dipelihara.
3.  **Keuangan dan stok dapat diaudit:** transaksi penting menggunakan
    ledger/pergerakan yang dapat ditelusuri; perubahan final tidak boleh
    diam-diam.
4.  **Pelanggan tidak wajib login:** guest checkout tetap tersedia;
    login opsional memberi akses ke riwayat, loyalitas, dan Order Ahead.
5.  **Server adalah sumber konfirmasi:** status pembayaran dan transaksi
    yang memerlukan server tidak boleh dianggap berhasil hanya karena
    redirect browser atau tampilan lokal.
6.  **PWA responsif:** mendukung penggunaan di ponsel, tablet, dan
    desktop; fungsi yang bergantung server membutuhkan koneksi yang
    valid.
7.  **Konfigurasi per tenant/cabang:** menu, jam buka, logo, tema,
    pajak, metode pembayaran, dan kebijakan operasional dapat diatur
    sesuai hak akses.
8.  **Keamanan dan privasi:** akses berbasis peran, isolasi
    tenant/cabang, audit trail, dan pengumpulan data seperlunya.

------------------------------------------------------------------------

## 2. Sasaran Pengguna dan Jenis Usaha

-   Warung makan dan warung nasi.
-   Usaha snack, kentang crispy, dan street food.
-   Kedai kopi, teh, jus, dan minuman.
-   Bakery, roti, dan pastry.
-   Bakso, mie, ayam, seafood, dan restoran keluarga.
-   Food court dan tenant makanan.
-   Grup usaha dengan beberapa cabang.
-   Usaha kecil yang hanya memiliki satu kasir hingga restoran dengan
    banyak karyawan dan stasiun dapur.

Sistem harus dapat diaktifkan secara bertahap. Usaha kecil tidak
diwajibkan memakai semua modul, sedangkan fitur lanjutan dapat
diaktifkan sesuai paket dan kebutuhan tenant.

------------------------------------------------------------------------

## 3. Teknologi dan Arsitektur

### 3.1 Stack yang ditetapkan

-   **Backend:** Laravel 13.
-   **PHP:** PHP 8.4 atau versi kompatibel yang didukung oleh Laravel
    dan seluruh dependency produksi.
-   **UI web:** Blade, Livewire, Alpine.js.
-   **Database utama:** MySQL.
-   **Cache, antrean, dan dukungan proses latar:** Redis dan Laravel
    Queue.
-   **Penjadwalan tugas:** Laravel Scheduler.
-   **Proses antrean:** Supervisor pada server Linux.
-   **Web server:** Nginx.
-   **Server target:** Ubuntu Server 24.04 LTS.
-   **Akses:** HTTPS.
-   **Antarmuka pelanggan dan operasional:** Progressive Web App (PWA)
    responsif.

Versi dependency harus dikunci melalui lockfile, diuji di staging, dan
diperbarui dengan prosedur upgrade terkontrol. Kompatibilitas versi PHP,
Laravel, database, dan paket harus diverifikasi sebelum deployment.

### 3.2 Bentuk arsitektur

Gunakan **modular monolith multi-tenant** sebagai fondasi awal. Setiap
domain memiliki batas tanggung jawab yang jelas, layanan aplikasi,
validasi, kebijakan akses, dan event internal. Pemisahan menjadi layanan
terpisah hanya dilakukan bila skala dan kebutuhan operasional
benar-benar membutuhkannya.

Modul utama: - Identity, Tenant & Branch. - Company Profile & Theme. -
Customer & CRM. - Menu & Pricing. - Ordering & Order Ahead. - POS &
Payment. - Table & QR Session. - Kitchen/KDS & Production. - Recipe,
Inventory & Purchasing. - Finance & Accounting. - Cashier Closing &
Closing History. - Employee, Shift & Attendance. - Payroll. -
Notification & WhatsApp Gateway. - Reporting & Analytics. - SaaS
Subscription & Tenant Administration.

### 3.3 PWA dan batas offline

PWA dapat dipasang ke layar utama perangkat. Static assets dapat dicache
untuk membantu pemuatan antarmuka. Namun: - Pesanan online baru dianggap
diterima setelah server mengonfirmasi. - Pembayaran baru dianggap sukses
setelah verifikasi callback/API server dari provider. - Jika koneksi
terputus, UI harus menjelaskan status dan mencegah pengiriman ganda. -
Aksi yang diulang harus menggunakan idempotency key atau mekanisme
setara. - Jangan mengklaim semua fitur dapat berjalan offline.

------------------------------------------------------------------------

## 4. Multi-Tenant, Cabang, dan SaaS

### 4.1 Isolasi data

Setiap tenant mempunyai identitas, profil perusahaan, branding,
konfigurasi, pengguna, menu, pelanggan, transaksi, laporan, dan cabang
masing-masing. Setiap data operasional yang relevan harus memiliki
`tenant_id`; data yang berlaku per cabang juga menggunakan `branch_id`.

Aturan: - Seluruh query, job queue, event, file, cache, dan laporan
harus menjaga konteks tenant. - Tenant tidak boleh mengakses data tenant
lain. - Hak akses cabang diberlakukan di backend, bukan hanya
menyembunyikan menu UI. - Uji otomatis wajib mencakup akses silang
tenant dan cabang. - File pelanggan, dokumen, slip gaji, dan laporan
sensitif harus disimpan serta diakses dengan kebijakan otorisasi yang
sesuai.

### 4.2 Paket SaaS

Platform mendukung paket berlangganan yang dapat membatasi atau
mengaktifkan: - Jumlah cabang. - Jumlah pengguna/karyawan. - Modul yang
aktif. - Volume atau jenis integrasi. - Branding, domain, dan fitur
lanjutan. - Batas penggunaan yang sesuai dengan paket.

Status langganan, masa percobaan, jatuh tempo, pembayaran, dan
penonaktifan harus memiliki aturan yang jelas tanpa menghapus data
tenant.

### 4.3 Multi-cabang

Pemilik yang berwenang dapat melihat laporan per cabang dan konsolidasi
grup. Transaksi tetap dicatat di cabang asal. Laporan konsolidasi tidak
boleh menghitung saldo cabang dua kali.

------------------------------------------------------------------------

## 5. Profil Perusahaan, Logo, dan Tema

### 5.1 Pengaturan Profil Perusahaan

Sediakan halaman khusus untuk: - Nama perusahaan/restoran. - Logo
perusahaan. - Alamat, nomor telepon/WhatsApp, email. - Identitas usaha
atau informasi legal yang relevan. - Pengaturan cabang, alamat cabang,
kontak, dan lokasi. - Jam operasional. - Informasi yang dicetak pada
nota dan dokumen.

Logo: - Unggah format gambar yang didukung seperti PNG, JPG, atau
WebP. - Preview sebelum menyimpan. - Validasi format, ukuran, dan
dimensi. - Logo dapat diganti atau dihapus dengan konfirmasi. - Logo
tenant terisolasi dari tenant lain. - Branding per cabang dapat
diaktifkan bila dibutuhkan. - Gunakan pada PWA pelanggan, QR menu, POS,
nota, invoice, laporan PDF, dan template notifikasi yang mendukung logo.

### 5.2 Pengaturan Tema Global

Sediakan satu halaman pengaturan tema dengan: - Light, dark, dan system
mode. - Preset warna serta warna kustom HEX. - Warna utama, aksen,
background, surface, teks, border, dan status. - Preview langsung dan
opsi reset. - Pengaturan font dan gaya tampilan yang didukung. -
Pemeriksaan kontras dan keterbacaan. - Tema tenant dan preferensi
pengguna dapat dibedakan. - Warna status harus mempertahankan makna dan
keterbacaan.

### 5.3 Widget cuaca dan lokasi

-   Widget cuaca menggunakan lokasi cabang aktif yang telah
    dikonfigurasi.
-   Tampilkan kondisi, suhu, waktu pembaruan terakhir, serta status data
    lama/gagal diperbarui.
-   Widget lokasi menampilkan nama/alamat cabang dan tautan peta bila
    tersedia.
-   Pemilik dapat mengaktifkan/menonaktifkan widget.
-   Lokasi kantor pusat tidak boleh otomatis dipakai untuk semua cabang.

------------------------------------------------------------------------

## 6. Modul Menu dan Produk

Fitur: - Kategori menu dan subkategori. - Foto produk, deskripsi, harga,
status aktif, dan ketersediaan. - Varian ukuran, level pedas, topping,
add-on, pilihan gula/es, dan modifier lain. - Harga dan ketersediaan
dapat berbeda per cabang. - Penjadwalan menu berdasarkan hari/jam. -
Penandaan habis sementara. - Paket/combo dan bundling. - Harga dine-in,
takeaway, atau kanal lain jika kebijakan tenant membutuhkannya. -
Riwayat perubahan harga. - Kontrol siapa yang boleh mengubah harga dan
siapa yang harus menyetujui perubahan.

------------------------------------------------------------------------

## 7. Pelanggan, Login, dan Order Ahead

### 7.1 Guest checkout dan akun pelanggan

Pelanggan tidak diwajibkan membuat akun untuk transaksi biasa, QR meja,
atau pemesanan yang diizinkan tenant. Pelanggan yang ingin fitur
tambahan dapat mendaftar/login.

Fitur akun: - Profil pelanggan dan kontak yang diperlukan. - Riwayat
pesanan dan nota digital. - Poin, voucher, dan level loyalitas jika
diaktifkan. - Preferensi dan promosi dengan persetujuan yang sesuai. -
Alamat tersimpan jika layanan antar diaktifkan di kemudian hari.

### 7.2 Order Ahead dari rumah

Alur: 1. Pelanggan login atau melanjutkan sebagai tamu jika diizinkan.
2. Memilih cabang. 3. Melihat menu, harga, modifier, dan ketersediaan
cabang tersebut. 4. Menambahkan item ke keranjang. 5. Memilih ambil
secepatnya atau slot waktu pengambilan. 6. Melihat total biaya dan
ketentuan. 7. Memilih metode pembayaran yang diaktifkan. 8. Mendapat
konfirmasi pesanan setelah server menerimanya. 9. Memantau status
pesanan. 10. Menunjukkan kode pengambilan ketika mengambil pesanan.

Aturan: - Slot pickup mengikuti jam operasional, kapasitas dapur,
ketersediaan bahan, dan batas jumlah pesanan. - Ketersediaan dan harga
divalidasi kembali di server saat checkout. - Kode pengambilan harus
unik atau aman untuk sesi terkait dan diverifikasi oleh staf. - Status
"siap diambil" harus dikonfirmasi staf, bukan hanya berdasarkan waktu
estimasi. - Awal pengembangan berfokus pada pickup; integrasi delivery
merupakan tahap lanjutan.

### 7.3 Notifikasi pelanggan

Status yang dapat diberitahukan: - Pesanan diterima. - Pembayaran
terkonfirmasi. - Pesanan sedang diproses. - Pesanan siap diambil. -
Pesanan selesai atau dibatalkan sesuai aturan.

Kanal dapat berupa UI/PWA, WhatsApp Gateway, atau kanal lain yang
dikonfigurasi. Pengiriman pesan perlu antrean, retry, log, dan kontrol
persetujuan promosi.

------------------------------------------------------------------------

## 8. QR Menu, Meja, dan Sesi Dine-In

-   Setiap meja dapat memiliki QR unik yang mengidentifikasi tenant,
    cabang, dan meja.
-   QR dapat dicetak, dinonaktifkan, dan dibuat ulang.
-   Token divalidasi oleh server.
-   QR mengidentifikasi meja, bukan memberikan otorisasi untuk melihat
    pesanan orang lain.
-   Satu meja memiliki sesi aktif untuk satu kelompok pelanggan pada
    satu waktu.
-   Beberapa pelanggan di meja yang sama dapat menambahkan item ke sesi
    yang sama sesuai kebijakan.
-   Mendukung peta meja, status tersedia/terisi/menunggu pembayaran,
    pindah meja, dan penggabungan meja dengan kontrol.
-   Mendukung split bill berdasarkan item atau jumlah sesuai kebijakan.
-   Ketika sesi ditutup, sesi baru dibuat untuk kelompok berikutnya.

------------------------------------------------------------------------

## 9. POS dan Pembayaran

### 9.1 POS

-   Dine-in, takeaway, dan Order Ahead.
-   Modifier, topping, catatan item, diskon, pajak, dan service charge
    sesuai konfigurasi.
-   Pindah meja, gabung meja, split bill, dan pembatalan terkontrol.
-   Pencetakan nota.
-   Pengiriman item ke stasiun dapur/bar yang tepat.
-   Audit atas diskon, perubahan harga, pembatalan, refund, dan tindakan
    sensitif.
-   Kontrol akses per jabatan dan persetujuan manajer untuk transaksi
    tertentu.

### 9.2 Pembayaran

Metode mengikuti provider dan merchant yang tersedia, misalnya: -
Tunai. - QRIS. - E-wallet. - Virtual account atau transfer. - Metode
lain yang didukung dan dikonfigurasi.

Aturan: - Pembayaran online diverifikasi dari callback/API server
provider. - Redirect browser tidak cukup untuk menyatakan pembayaran
berhasil. - Gunakan idempotency dan proteksi callback ganda. - Catat
pending, sukses, gagal, kedaluwarsa, refund, biaya provider, dan
settlement. - Lakukan rekonsiliasi pembayaran dan settlement. -
Pembayaran tunai baru berstatus lunas setelah dikonfirmasi sesuai
prosedur kasir. - Refund harus terotorisasi dan diaudit.

------------------------------------------------------------------------

## 10. Nota dan Pencetakan

-   Nota digital dapat dibuka pelanggan melalui PWA.
-   Opsi pengiriman WhatsApp sesuai konfigurasi.
-   PDF dapat diunduh jika diaktifkan.
-   Printer thermal 58/80 mm dapat digunakan sesuai dukungan perangkat.
-   Auto-print mungkin memerlukan browser, driver, atau print bridge
    lokal; jangan menjanjikan semua printer dapat mencetak otomatis dari
    browser.
-   Nota berisi nomor unik, tenant/cabang, waktu, item, modifier,
    diskon, pajak/service charge, total, metode/status pembayaran, dan
    identitas kasir sesuai kebijakan.
-   Simpan riwayat cetak ulang bila diperlukan.
-   Logo dan profil perusahaan muncul pada dokumen yang mendukungnya.

------------------------------------------------------------------------

## 11. Dapur, KDS, dan Produksi

### 11.1 Kitchen Display System

-   Antrean pesanan real-time.
-   Stasiun terpisah: dapur panas, minuman/bar, bakery, atau stasiun
    lain.
-   Status antrean: baru, diterima, diproses, siap, selesai, dibatalkan.
-   Timer dan estimasi waktu.
-   Prioritas berdasarkan urutan, waktu pickup, dan kebijakan
    operasional.
-   Peringatan keterlambatan.
-   Status siap dikonfirmasi staf.
-   Tindakan pembatalan setelah produksi dimulai harus tercatat dan
    dapat memerlukan persetujuan.

### 11.2 Perencanaan kapasitas dapur

-   Slot Order Ahead dibatasi berdasarkan kapasitas dapur.
-   Hitung beban pesanan per stasiun dan estimasi durasi.
-   Periksa bahan sebelum menerima pesanan.
-   Persiapan bahan dan produksi batch sebelum jam ramai.
-   Pantau antrean dan waktu tunggu.
-   Penjadwalan membantu keputusan operasional, tetapi estimasi tidak
    boleh dianggap jaminan waktu absolut.

### 11.3 Produksi dan batch

-   Bahan mentah, barang setengah jadi, dan produk jadi.
-   Batch produksi, tanggal/waktu, jumlah hasil, dan penggunaan bahan.
-   Pencatatan yield aktual dan susut.
-   Catatan batch dapat ditelusuri ke stok bahan.
-   Proses konversi seperti ayam utuh menjadi potongan harus memakai
    yield aktual yang dikonfigurasi, bukan asumsi universal.

------------------------------------------------------------------------

## 12. Resep, Satuan, HPP, dan Margin

### 12.1 Resep/BOM

Setiap menu dapat memiliki resep/BOM yang berisi:

- Bahan dan kuantitas serta satuan pemakaian.
- Yield atau hasil produksi aktual.
- Bahan langsung seperti kentang, minyak, tepung, protein, dan kemasan.
- Ingredient hasil produksi (sub-recipe) seperti saus racikan, seasoning racikan, sambal, marinasi, adonan tepung, kaldu, topping, dan bahan setengah jadi lain.
- Variasi resep berdasarkan ukuran/varian, versi resep, dan tanggal efektif.

### 12.1.1 Ingredient hasil produksi (Sub-Recipe) — fitur standar

- Master ingredient harus membedakan bahan baku beli/langsung pakai dan
  ingredient hasil produksi yang dibuat dari resep lain. Keduanya dapat
  dipilih sebagai ingredient dalam resep menu.
- Saus, seasoning/bumbu tabur, sambal, marinasi, adonan, kaldu, dan
  racikan lain dapat dibuat sebagai resep turunan (sub-recipe) yang
  dapat digunakan kembali pada satu atau banyak menu.
- Setiap sub-recipe memiliki daftar bahan, takaran, satuan, jumlah hasil
  aktual (yield), susut/waste, biaya produksi, HPP per satuan dasar,
  tanggal/batch produksi, dan saldo stok tersendiri.
- Saat batch sub-recipe diproduksi, sistem mengurangi stok bahan baku
  berdasarkan konsumsi aktual yang disahkan dan menambah stok hasil
  produksi berdasarkan yield aktual. Perbedaan yield atau susut dicatat
  sebagai variance/waste dengan alasan dan audit trail.
- Saat menu terjual atau bahan racikan dipakai dalam produksi lain,
  stok sub-recipe berkurang otomatis sesuai takaran resep dan HPP menu
  mengambil biaya dari sub-recipe tersebut.
- Satu sub-recipe dapat digunakan oleh banyak resep tanpa perlu
  menduplikasi komposisi atau menghitung biaya bahan yang sama berulang.
- Sistem harus mencegah penghitungan ganda: apabila resep menu memakai
  sub-recipe sebagai ingredient, HPP menu mengambil HPP sub-recipe dan
  tidak menambahkan lagi biaya bahan mentah penyusun sub-recipe secara
  terpisah.
- Perubahan harga bahan baku memengaruhi kalkulasi batch/HPP berikutnya;
  snapshot biaya batch dan transaksi yang sudah tercatat tetap terjaga.
- Dukung satuan dasar yang sesuai (misalnya gram atau mililiter),
  konversi satuan yang dikonfigurasi, pembulatan terkontrol, dan
  penelusuran dari menu ke batch sub-recipe hingga bahan baku asal.

### 12.2 Satuan dan konversi

Dukung satuan seperti: - Gram, kilogram, ons. - Mililiter, liter. - Pcs,
butir, buah, porsi, tusuk, potong, ekor. - Ikat, pack, sachet, renteng,
lusin, kodi, rim. - Tray, krat, botol, kaleng, cup, box, karton,
karung/sak, jeriken.

Konversi kemasan harus dapat diatur sesuai isi aktual, misalnya satu
karton berisi jumlah unit tertentu. Konversi produksi memakai yield dan
faktor yang dikonfigurasi.

### 12.3 Perhitungan HPP

-   HPP teoritis berdasarkan resep dan biaya bahan.
-   HPP produksi aktual berdasarkan pemakaian dan yield.
-   HPP sub-recipe per gram/ml/pcs atau satuan hasil yang dikonfigurasi.
-   Biaya minyak, bumbu, saus, seasoning, racikan, dan kemasan dapat diperhitungkan.
-   Saat sub-recipe dipakai dalam resep lain, biaya diambil dari HPP stok sub-recipe agar tidak terjadi penghitungan ganda.
-   Margin dan simulasi harga jual.
-   Perbandingan HPP teoritis dan aktual.
-   Simpan snapshot biaya saat transaksi/produksi sehingga perubahan
    harga bahan di masa depan tidak mengubah nilai historis.
-   Metode valuasi stok seperti FIFO atau weighted average dapat
    dikonfigurasi dengan konsistensi akuntansi.

------------------------------------------------------------------------

## 13. Persediaan, Pembelian, dan Kontrol Kebocoran

### 13.1 Persediaan

-   Master bahan/barang, kategori, satuan, konversi, dan lokasi
    penyimpanan, dengan penanda bahan beli, bahan langsung pakai, atau
    hasil produksi/sub-recipe.
-   Saldo dan ledger terpisah untuk bahan baku dan hasil racikan.
-   Penerimaan barang dan retur supplier.
-   Transfer antar lokasi/cabang dengan otorisasi.
-   Pemakaian resep dan produksi.
-   Penyesuaian stok dengan alasan.
-   Stock opname dan rekonsiliasi.
-   Stok minimum dan peringatan bahan habis.
-   Ledger pergerakan stok (`stock_movements`) sebagai jejak sumber
    perhitungan.
-   Snapshot saldo dapat digunakan untuk performa, tetapi harus dapat
    direkonsiliasi terhadap ledger.

### 13.2 Kontrol susut dan kebocoran stok --- fitur inti

-   Catat barang rusak, basi, kedaluwarsa, tumpah, gagal produksi, dan
    waste lain.
-   Wajib pilih alasan dan kuantitas.
-   Persetujuan sesuai batas nilai/kuantitas.
-   Bandingkan stok teoritis dengan stok fisik.
-   Analisis pemakaian bahan terhadap penjualan dan resep.
-   Peringatan selisih berulang atau pemakaian tidak wajar.
-   Laporan nilai kerugian per bahan, menu, cabang, periode, dan
    pengguna.
-   Semua koreksi memiliki audit trail.

### 13.3 Pembelian dan supplier

-   Master supplier dan informasi kontak.
-   Permintaan pembelian, purchase order, penerimaan, invoice, utang
    supplier, dan pembayaran.
-   Perbandingan harga pembelian.
-   Riwayat harga dan lead time.
-   Persetujuan pembelian sesuai batas.
-   Retur supplier dan selisih penerimaan.
-   Integrasi ke stok dan keuangan.

------------------------------------------------------------------------

## 14. Setup Awal Usaha dan Saldo Awal

Sediakan wizard **Setup Awal Usaha & Saldo Awal** untuk migrasi dari
pencatatan lama.

Dukung: - Saldo awal kas. - Saldo awal bank dan e-wallet. - Kuantitas
dan nilai persediaan awal. - Utang supplier. - Piutang pelanggan jika
digunakan. - Uang muka. - Modal pemilik. - Aset, liabilitas, dan saldo
laba awal yang relevan. - Import Excel dengan validasi. - Rekonsiliasi
total debit dan kredit. - Approval dan tanggal efektif. - Audit trail
finalisasi.

Saldo per cabang harus direkonsiliasi dan konsolidasi tidak boleh
menghitung saldo dua kali. Setelah final, koreksi menggunakan adjustment
resmi, bukan menimpa data tanpa jejak.

------------------------------------------------------------------------

## 15. Keuangan dan Akuntansi

Modul keuangan harus mencakup: - Kas dan bank multi-akun. - Penerimaan
dan pengeluaran. - Utang dan piutang jika diaktifkan. - Jurnal umum dan
buku besar. - Laporan laba rugi. - Arus kas. - Neraca. - Rekonsiliasi
kasir dan payment gateway. - Rekonsiliasi settlement dan biaya
provider. - Valuasi persediaan dan HPP. - Closing periode dan laporan
per cabang/konsolidasi. - Ekspor Excel/PDF. - Hak akses dan audit.

Gunakan prinsip double-entry untuk transaksi akuntansi yang diposting;
setiap jurnal harus seimbang. Status pembayaran, utang, dan transaksi
harus dibedakan dengan jelas.

### Pajak

Pengaturan pajak harus fleksibel per tenant/cabang dan mengikuti
ketentuan yang berlaku. Tarif dan aturan yang berubah tidak boleh
ditanam permanen tanpa mekanisme pembaruan serta pengujian.

------------------------------------------------------------------------

## 16. Closing Kasir dan History Closing

### 16.1 Form closing

Closing harus menghitung kas seharusnya berdasarkan: - Saldo awal kas. -
Penjualan tunai. - Penerimaan kas lain. - Pengeluaran kas. -
Setoran/transfer kas yang dicatat. - Penyesuaian yang sah.

Kasir dapat memilih: - Menginput total uang fisik. - Menghitung
berdasarkan jumlah setiap pecahan uang kertas dan logam.

Sistem menghitung selisih kas lebih/kurang. Pembayaran non-tunai
direkonsiliasi terpisah dari uang fisik di laci.

### 16.2 History Closing --- fitur inti

Setiap closing final tersimpan sebagai arsip yang dapat dicari dan
difilter berdasarkan: - Tanggal/periode. - Tenant dan cabang. - Kasir
dan shift. - Nomor closing. - Status sesuai/selisih. - Status
persetujuan.

Rincian historis mencakup saldo awal, penjualan tunai, penerimaan lain,
pengeluaran, setoran, kas seharusnya, hitung fisik/per pecahan, selisih,
rekonsiliasi non-tunai, alasan, penyetuju, dan waktu finalisasi.

Aturan: - Closing final tidak boleh diubah atau dihapus diam-diam. -
Koreksi/reopen memerlukan kewenangan, alasan, dan before/after audit. -
History dapat diekspor ke PDF/Excel dan ditelusuri ke transaksi
terkait. - Hak akses mengikuti peran dan cabang.

------------------------------------------------------------------------

## 17. Manajemen Karyawan

Sediakan halaman khusus untuk: - ID karyawan dan profil. - Kontak dan
status kerja. - Jabatan: pemilik, manajer, kasir, koki, asisten koki,
barista, pramusaji, gudang, admin, cleaning service, dan jabatan lain. -
Cabang penugasan. - Tanggal mulai/akhir dan informasi kontrak sesuai
kebutuhan. - Akun login terpisah dari data pegawai; tidak semua pegawai
wajib punya akun. - Hak akses berbasis peran dan izin tindakan. - Jadwal
shift dan absensi. - Pengajuan izin/cuti. - Hubungan dengan closing
kasir, aktivitas, dan payroll. - Riwayat perubahan dan audit.

------------------------------------------------------------------------

## 18. Jadwal Shift Karyawan

Dukung banyak karyawan dan banyak cabang.

Fitur: - Jadwal harian, mingguan, dan bulanan. - Jadwal per karyawan,
jabatan, stasiun, dan cabang. - Master shift yang dapat dikonfigurasi. -
Deteksi bentrok jadwal dan pemeriksaan kebutuhan staf per bagian. - Hari
libur, cuti, izin, dan pergantian shift. - Permintaan tukar shift dengan
persetujuan manajer. - Publikasi jadwal dan riwayat revisi. - Integrasi
ke absensi dan payroll. - Ekspor/cetak Excel dan PDF. - Papan jadwal
yang mudah dibaca untuk ditempel.

### Kode papan shift

Kode standar yang dapat dikonfigurasi: - `P` = Pagi. - `S` = Siang. -
`M` = Malam. - `OFF` = Libur. - `C` = Cuti. - `I` = Izin. - `L` = Libur
nasional (jika digunakan).

Tampilan papan berupa tabel nama karyawan dan kolom Senin--Minggu.
Mendukung cabang, periode minggu, warna pembeda, dan format cetak A4
landscape. Jika jumlah staf terlalu banyak untuk satu halaman yang
terbaca, jadwal dapat dibagi berdasarkan bagian atau cabang.

Template Excel kerja telah dirancang dengan lembar Petunjuk, Data
Karyawan, Master Shift, Jadwal Mingguan, Rekap Kehadiran, dan Papan
Shift.

------------------------------------------------------------------------

## 19. Absensi GPS

### 19.1 Spesifikasi yang disetujui

Metode utama: **GPS/lokasi perangkat saat check-in dan check-out**.

Fitur: - Check-in dan check-out. - Jadwal shift dan cabang aktif. -
Radius/geofence cabang yang dapat diatur. - Catatan waktu server,
koordinat yang diperlukan, dan akurasi GPS. - Status terlambat, pulang
awal, missing punch, tidak hadir, izin, sakit, dan cuti. - Istirahat
jika dikonfigurasi. - Pengajuan izin, sakit, dan cuti. - Rekap
Excel/PDF. - Integrasi lembur dengan payroll. - Persetujuan manajer dan
audit trail.

### 19.2 Privasi dan kontrol

-   Minta izin lokasi perangkat secara jelas.
-   Lokasi digunakan untuk kejadian absensi, bukan pelacakan
    terus-menerus di luar proses absensi.
-   GPS tidak dianggap bukti anti-manipulasi yang sempurna.
-   Jika lokasi tidak tersedia, sediakan prosedur koreksi manual berizin
    dengan alasan dan audit.
-   Lembur harus ditinjau/disetujui, bukan otomatis dianggap sah hanya
    karena jam aktual melebihi jadwal.
-   Pengajuan izin/cuti memiliki status draft, diajukan, disetujui,
    ditolak, atau dibatalkan.
-   Ekspor laporan dibatasi berdasarkan hak akses.

------------------------------------------------------------------------

## 20. Payroll dan Penggajian

### 20.1 Metode yang wajib didukung

-   Gaji bulanan.
-   Upah harian.
-   Upah per shift.
-   Upah per jam.
-   Komisi.
-   Borongan.

### 20.2 Fitur

-   Gaji pokok/upah.
-   Tunjangan.
-   Bonus dan komisi.
-   Lembur.
-   Absensi, izin, dan cuti.
-   Kasbon dan cicilan karyawan.
-   THR dan pesangon sesuai ketentuan yang berlaku.
-   BPJS dan tunjangan.
-   Perhitungan PPh 21.
-   Slip gaji PDF.
-   Persetujuan payroll.
-   Pembayaran tunai/transfer.
-   Riwayat per periode, pegawai, cabang, dan status.
-   Integrasi dengan keuangan dan akuntansi.
-   Audit trail perubahan dan koreksi.

### 20.3 Alur payroll

1.  Tetapkan data kerja, kontrak, metode upah, nilai, dan tanggal
    efektif.
2.  Tarik absensi, shift, lembur, cuti, komisi, dan borongan yang sudah
    diverifikasi.
3.  Hitung penghasilan bruto, tunjangan, insentif, potongan, kasbon,
    iuran, dan pajak.
4.  Review dan setujui.
5.  Catat pembayaran secara terpisah dari perhitungan/persetujuan.
6.  Terbitkan slip PDF privat.
7.  Posting jurnal akuntansi seimbang dan simpan snapshot periode.

Status: Draft, Dihitung, Ditinjau, Disetujui, Dibayar Sebagian, Dibayar,
dan Dikoreksi.

Payroll final tidak boleh diedit diam-diam. Koreksi harus melalui
penyesuaian/pembalikan resmi dengan alasan dan audit before/after. Data
gaji hanya dapat diakses pihak berwenang dan karyawan yang bersangkutan.

### 20.4 Kepatuhan

Aturan upah minimum, lembur, THR, pesangon, BPJS, dan PPh 21 harus
mengikuti peraturan Indonesia yang berlaku dan dapat diperbarui. Jangan
hardcode tarif yang berpotensi kedaluwarsa. Perhitungan harus diuji
dengan contoh kasus dan ditinjau oleh pihak yang memahami kepatuhan
penggajian.

------------------------------------------------------------------------

## 21. Pelanggan, CRM, dan Loyalitas

-   Data pelanggan opsional dan guest checkout.
-   Riwayat pesanan dan nota.
-   Poin, voucher, tingkat pelanggan, dan promo jika diaktifkan.
-   Segmentasi berdasarkan perilaku transaksi yang sah.
-   Persetujuan dan opt-out untuk promosi.
-   Integrasi dengan Order Ahead, QR menu, POS, dan WhatsApp Gateway.
-   Pelanggan dapat memilih cabang saat memesan.
-   Informasi pelanggan tenant harus terisolasi dan tidak dibagikan
    antar tenant tanpa dasar yang sah.

------------------------------------------------------------------------

## 22. WhatsApp Gateway dan Notifikasi

-   Integrasi provider yang sesuai dan tersedia.
-   Notifikasi status pesanan dan pembayaran.
-   Pengiriman nota digital bila diaktifkan.
-   Notifikasi pesanan siap diambil.
-   Promosi hanya sesuai persetujuan pelanggan.
-   Kredensial provider disimpan aman dan terisolasi per tenant.
-   Template, antrean, retry, log pengiriman, dan penanganan kegagalan.
-   Patuhi kebijakan platform pesan dan ketentuan privasi yang berlaku.

------------------------------------------------------------------------

## 23. Audit, Anti-Fraud, dan Persetujuan

### 23.1 Audit kasir --- fitur inti

Catat: - Void/pembatalan setelah pesanan dikirim ke dapur. - Diskon dan
perubahan harga. - Refund. - Penghapusan item. - Pembukaan laci kas. -
Pengeluaran kas. - Penyesuaian stok. - Reopen atau koreksi closing. -
Persetujuan manajer. - Perubahan pengaturan keuangan sensitif.

Setiap peristiwa penting menyimpan pelaku, waktu server, objek, alasan,
dan nilai sebelum/sesudah jika relevan. Log final tidak boleh dapat
diubah secara biasa oleh pengguna operasional.

### 23.2 Persetujuan

Aturan approval dapat diatur berdasarkan: - Nilai transaksi. - Besar
diskon. - Selisih stok. - Refund. - Koreksi closing. - Lembur. -
Payroll. - Pembelian dan pengeluaran kas.

------------------------------------------------------------------------

## 24. Laporan dan Analitik

Laporan yang disediakan: - Penjualan per hari, periode, menu, kanal,
kasir, dan cabang. - Menu terlaris dan margin menu. - HPP dan biaya
bahan. - Stok teoritis vs fisik. - Waste dan nilai kerugian. - Pembelian
dan performa supplier. - Closing kasir dan selisih. - Rekonsiliasi
pembayaran. - Laba rugi, arus kas, neraca, buku besar. - Jadwal,
absensi, keterlambatan, dan lembur. - Payroll dan biaya karyawan. -
Kinerja cabang dan konsolidasi. - Ekspor Excel/PDF dengan kontrol akses.

### AI dan analitik lanjutan

Tahap lanjutan dapat mencakup: - Prediksi permintaan dan kebutuhan
bahan. - Rekomendasi persiapan produksi. - Analisis menu yang margin-nya
rendah. - Peringatan anomali stok dan kas. - Rekomendasi pembelian. -
Ringkasan performa bisnis.

Rekomendasi AI bersifat pendukung keputusan; transaksi keuangan atau
perubahan stok tidak boleh dilakukan otomatis tanpa aturan otorisasi.

------------------------------------------------------------------------

## 25. Modul Lanjutan

Dapat dikembangkan sesuai prioritas dan integrasi yang tersedia: -
Reservasi meja. - Delivery dan integrasi platform pihak ketiga bila API
tersedia. - Maintenance peralatan seperti freezer, kulkas, dan kompor. -
CRM/loyalty lanjutan. - Integrasi akuntansi/pajak lanjutan. - Domain
kustom dan branding tenant. - Integrasi ke Almara OS jika ditentukan
melalui kontrak API yang aman.

------------------------------------------------------------------------

## 26. Rancangan Domain Database

Daftar berikut adalah kelompok tabel konseptual; nama akhir harus
disesuaikan saat desain ERD dan migration.

### Tenant dan identitas

-   `tenants`
-   `branches`
-   `users`
-   `roles`
-   `permissions`
-   `user_branch_access`
-   `tenant_subscriptions`
-   `company_profiles`
-   `branch_profiles`
-   `theme_settings`
-   `media_assets`

### Pelanggan dan menu

-   `customers`
-   `customer_addresses`
-   `customer_consents`
-   `loyalty_accounts`
-   `loyalty_transactions`
-   `menus`
-   `menu_categories`
-   `menu_variants`
-   `menu_modifiers`
-   `branch_menu_availability`
-   `price_histories`

### Pesanan, meja, dan pembayaran

-   `dining_tables`
-   `table_qr_tokens`
-   `table_sessions`
-   `orders`
-   `order_items`
-   `order_item_modifiers`
-   `order_status_histories`
-   `pickup_slots`
-   `pickup_codes`
-   `payments`
-   `payment_events`
-   `refunds`
-   `payment_settlements`
-   `receipts`

### Dapur dan produksi

-   `kitchen_stations`
-   `kitchen_tickets`
-   `kitchen_ticket_events`
-   `recipes` (termasuk tipe resep menu dan sub-recipe)
-   `recipe_versions`
-   `recipe_items` (ingredient dapat berupa bahan baku atau hasil sub-recipe)
-   `production_batches` (batch produksi sub-recipe/produk)
-   `production_consumptions`
-   `production_outputs`
-   `waste_records`

### Stok dan pembelian

-   `inventory_items`
-   `units`
-   `unit_conversions`
-   `stock_locations`
-   `stock_movements`
-   `stock_balances` (snapshot opsional)
-   `stock_counts`
-   `stock_count_lines`
-   `suppliers`
-   `purchase_orders`
-   `purchase_order_items`
-   `goods_receipts`
-   `supplier_invoices`
-   `supplier_payments`
-   `supplier_returns`

### Keuangan dan closing

-   `cash_accounts`
-   `bank_accounts`
-   `cash_movements`
-   `bank_movements`
-   `expenses`
-   `journal_entries`
-   `journal_lines`
-   `accounting_periods`
-   `cashier_shifts`
-   `cash_closings`
-   `cash_closing_denominations`
-   `cash_closing_adjustments`
-   `cash_closing_approvals`
-   `opening_balances`
-   `opening_balance_lines`

### Karyawan, jadwal, absensi, payroll

-   `employees`
-   `employment_terms`
-   `employee_documents` (bila digunakan)
-   `employee_shift_templates`
-   `employee_schedules`
-   `shift_swap_requests`
-   `attendance_events`
-   `attendance_daily_summaries`
-   `attendance_corrections`
-   `leave_requests`
-   `overtime_requests`
-   `payroll_periods`
-   `payroll_runs`
-   `payroll_items`
-   `payroll_components`
-   `employee_advances`
-   `employee_advance_installments`
-   `payslips`
-   `payroll_payments`

### Sistem dan audit

-   `notifications`
-   `notification_templates`
-   `integration_credentials` (dengan penyimpanan rahasia yang aman)
-   `integration_logs`
-   `audit_logs`
-   `approval_requests`
-   `file_records`
-   `scheduled_reports`

### Aturan database umum

-   Tabel domain tenant harus mengikat `tenant_id`; data cabang juga
    menyimpan `branch_id` bila relevan.
-   Gunakan foreign key, unique constraints, indeks yang sesuai,
    transaksi database, dan soft delete hanya bila tepat.
-   Nomor pesanan, closing, dan dokumen harus aman terhadap transaksi
    bersamaan.
-   Gunakan idempotency untuk request yang dapat dikirim ulang.
-   Gunakan ledger pergerakan untuk stok dan uang; jangan mengandalkan
    satu angka saldo yang dapat diubah tanpa jejak.
-   Data final yang perlu dipertahankan harus memiliki snapshot atau
    versi.
-   Dokumen sensitif tidak boleh diakses melalui URL publik tanpa
    otorisasi.

------------------------------------------------------------------------

## 27. Struktur Folder Laravel yang Disarankan

Contoh struktur domain modular:

``` text
app/
├── Domain/
│   ├── Tenancy/
│   ├── Identity/
│   ├── CompanyProfile/
│   ├── Customers/
│   ├── Catalog/
│   ├── Ordering/
│   ├── POS/
│   ├── Payments/
│   ├── Tables/
│   ├── Kitchen/
│   ├── Production/
│   ├── Inventory/
│   ├── Purchasing/
│   ├── Finance/
│   ├── CashClosing/
│   ├── Employees/
│   ├── Scheduling/
│   ├── Attendance/
│   ├── Payroll/
│   ├── CRM/
│   ├── Notifications/
│   ├── Reporting/
│   └── SaaS/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   └── Requests/
├── Jobs/
├── Policies/
├── Providers/
└── Support/

resources/
├── views/
│   ├── layouts/
│   ├── components/
│   ├── tenant/
│   ├── customer/
│   ├── pos/
│   ├── kitchen/
│   ├── inventory/
│   ├── finance/
│   ├── closing/
│   ├── employees/
│   ├── scheduling/
│   ├── attendance/
│   ├── payroll/
│   └── reports/
├── js/
└── css/

database/
├── migrations/
├── factories/
└── seeders/

routes/
├── web.php
├── api.php
└── console.php

tests/
├── Feature/
├── Unit/
└── Integration/
```

Struktur final harus tetap konsisten dengan gaya Laravel, tidak membuat
abstraksi berlebihan, dan menetapkan batas modul yang jelas.

------------------------------------------------------------------------

## 28. Keamanan, Backup, dan Operasional

-   HTTPS wajib di lingkungan produksi.
-   Secrets dan kredensial provider tidak disimpan dalam source code.
-   Validasi server-side untuk semua transaksi.
-   Rate limit untuk login, OTP, API, dan endpoint sensitif.
-   Kebijakan password/session dan pemulihan akun yang aman.
-   Proteksi CSRF dan kontrol akses berbasis policy.
-   Log audit untuk tindakan sensitif.
-   Backup database dan file secara terjadwal.
-   Uji restore backup secara berkala.
-   Monitoring error, antrean, scheduler, database, disk, dan resource
    server.
-   Proses deploy dengan staging, migration terencana, dan rollback.
-   Uji beban, transaksi bersamaan, isolasi tenant, dan integrasi
    pembayaran.
-   Retensi data dan penghapusan mengikuti kebijakan serta kewajiban
    hukum.

------------------------------------------------------------------------

## 29. Roadmap Implementasi

Estimasi awal **14--22 minggu untuk tim kecil** adalah perkiraan
perencanaan dan dapat berubah berdasarkan ukuran tim, kualitas desain,
kompleksitas integrasi, dan cakupan pengujian.

### Fase 1 --- Fondasi SaaS

-   Tenant, cabang, autentikasi, role/permission.
-   Profil perusahaan, logo, tema.
-   Audit dasar.
-   CI/deployment staging, struktur modul, isolasi tenant.

### Fase 2 --- Menu, resep, HPP, dan stok

-   Master menu dan bahan.
-   Satuan dan konversi.
-   Resep/BOM, sub-recipe saus/seasoning/racikan, dan snapshot HPP.
-   Produksi batch sub-recipe, yield aktual, susut, dan stok hasil racikan.
-   Pemakaian sub-recipe otomatis per menu tanpa penghitungan biaya ganda.
-   Persediaan, ledger stok, supplier, pembelian.
-   Waste, stock opname, dan kontrol selisih.

### Fase 3 --- POS, pelanggan, pesanan, dan pembayaran

-   POS.
-   QR meja dan sesi meja.
-   Guest checkout dan akun pelanggan.
-   Order Ahead pickup dan slot waktu.
-   Payment gateway dan rekonsiliasi.
-   Nota, kode pickup, dan notifikasi.

### Fase 4 --- KDS dan produksi

-   Stasiun dapur.
-   Antrean dan status pesanan.
-   Perencanaan kapasitas.
-   Batch produksi, yield, dan waste.
-   Pengujian status real-time.

### Fase 5 --- Closing dan keuangan

-   Shift kasir.
-   Closing dan history closing.
-   Saldo awal.
-   Kas/bank, pengeluaran, jurnal, dan laporan.
-   Audit anti-fraud dan approval.

### Fase 6 --- Karyawan, jadwal, absensi, dan payroll

-   Manajemen karyawan.
-   Jadwal mingguan dan papan shift.
-   GPS check-in/out.
-   Izin, sakit, cuti, dan persetujuan lembur.
-   Payroll semua metode, kasbon, slip PDF, BPJS, pajak, THR/pesangon.
-   Pengujian kepatuhan dan integrasi akuntansi.

### Fase 7 --- SaaS lanjutan dan CRM

-   Paket berlangganan.
-   Loyalitas dan voucher.
-   Branding/domain tenant.
-   Reservasi dan delivery jika integrasi tersedia.
-   Analitik lanjutan dan AI.

### Fase 8 --- Stabilitas dan peluncuran

-   Uji keamanan tenant/cabang.
-   Uji transaksi bersamaan dan idempotency.
-   Uji restore backup.
-   Uji printer, pembayaran, dan notifikasi.
-   Pilot di usaha nyata, perbaikan, lalu peluncuran bertahap.

Urutan fase dapat disesuaikan, tetapi fondasi keamanan, audit, dan
isolasi tenant tidak boleh ditunda.

------------------------------------------------------------------------

## 30. Kriteria Penerimaan Utama

Sebelum peluncuran produksi, pastikan: 1. Tenant A tidak dapat membaca
atau mengubah data Tenant B. 2. Pengguna hanya dapat melakukan tindakan
sesuai role dan akses cabang. 3. Pembayaran tidak dianggap berhasil
tanpa verifikasi server. 4. Pesanan yang dikirim ulang tidak
menghasilkan transaksi ganda. 5. Closing menyimpan uang seharusnya, uang
fisik, selisih, persetujuan, dan history. 6. Stok dapat direkonsiliasi
dengan ledger pergerakan. 7. Perubahan harga/resep tidak mengubah
snapshot HPP historis. 8. Jadwal, absensi, lembur, dan payroll memiliki
jejak persetujuan. 9. Payroll yang final tidak bisa diedit tanpa
adjustment/audit. 10. Laporan keuangan memiliki jurnal berimbang. 11.
Data lokasi absensi tidak dikumpulkan secara terus-menerus di luar
proses absensi. 12. Logo, tema, profil, dan konfigurasi satu tenant
tidak bocor ke tenant lain. 13. Backup dapat dipulihkan dan proses
restore sudah diuji. 14. Kegagalan koneksi ditampilkan jelas dan tidak
menandai pesanan/pembayaran sukses secara keliru.

------------------------------------------------------------------------

## 31. Keputusan Produk yang Sudah Ditetapkan

-   Nama produk: **ALMARA Restaurant & Beverage OS**.
-   Model: SaaS multi-tenant dengan dukungan multi-cabang.
-   Stack utama: Laravel 13, PHP kompatibel, Blade, Livewire, Alpine.js,
    MySQL, Redis, Queue, Scheduler, Supervisor, Nginx, Ubuntu Server
    24.04 LTS.
-   PWA untuk pelanggan dan operasional.
-   Guest checkout tetap tersedia; login pelanggan opsional.
-   Order Ahead pickup termasuk fitur inti.
-   QR meja unik dengan sesi meja.
-   Payment gateway dengan verifikasi server-side.
-   Nota digital dan dukungan cetak sesuai perangkat.
-   Resep, HPP, yield, stok, pembelian, waste, dan produksi.
-   Closing kasir beserta History Closing yang dapat diaudit.
-   Manajemen Karyawan dan jadwal shift.
-   Papan shift siap cetak A4 landscape.
-   Absensi GPS saat check-in/check-out.
-   Payroll mendukung bulanan, harian, shift, jam, komisi, dan borongan.
-   Payroll memiliki slip PDF, kasbon/cicilan, lembur, absensi/cuti,
    THR/pesangon, BPJS, dan pajak.
-   Profil perusahaan dengan upload logo.
-   Pengaturan tema terpusat dan branding tenant.
-   Kontrol stok/waste, audit anti-fraud kasir, dan penjadwalan
    kapasitas dapur menjadi prioritas sejak awal.
-   Fitur delivery, reservasi, maintenance, CRM lanjutan, dan AI
    dikembangkan sesuai fase serta ketersediaan integrasi.

------------------------------------------------------------------------

## 32. Catatan Pengembangan

Dokumen ini adalah blueprint produk dan arsitektur tingkat sistem, bukan
pengganti ERD final, kontrak API, spesifikasi UI per halaman, migration
Laravel, atau tinjauan hukum/akuntansi. Sebelum implementasi tiap fase,
buat spesifikasi rinci, acceptance criteria, test cases, dan keputusan
yang dapat dilacak ke blueprint ini.

Untuk aturan ketenagakerjaan, pajak, pembayaran, privasi, dan integrasi
pihak ketiga, verifikasi peraturan dan dokumentasi resmi yang berlaku
saat implementasi.
