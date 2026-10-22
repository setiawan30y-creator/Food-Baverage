<?php

/**
 * Konfigurasi ALMARA Restaurant & Beverage OS.
 * @2026-Alifiandra
 */

return [
    'product_name' => 'ALMARA Restaurant & Beverage OS',
    'version' => '1.4.0',
    'signature' => '@2026-Alifiandra',

    /*
    | Peran & kemampuan. Hak akses diberlakukan di backend (middleware "ability:...").
    */
    'roles' => [
        'owner' => ['label' => 'Pemilik', 'abilities' => ['*']],
        'manager' => ['label' => 'Manajer', 'abilities' => [
            'dashboard', 'pos', 'orders', 'orders.void', 'kitchen', 'tables', 'menu', 'menu.price', 'inventory',
            'inventory.approve', 'purchasing', 'recipes', 'production', 'closing', 'closing.approve',
            'employees', 'schedule', 'attendance.self', 'attendance.manage', 'payroll', 'reports', 'finance', 'audit',
        ]],
        'admin' => ['label' => 'Admin', 'abilities' => [
            'dashboard', 'orders', 'menu', 'inventory', 'purchasing', 'recipes', 'employees', 'schedule',
            'attendance.self', 'attendance.manage', 'payroll', 'reports',
        ]],
        'cashier' => ['label' => 'Kasir', 'abilities' => ['dashboard', 'pos', 'orders', 'tables', 'closing', 'attendance.self']],
        'kitchen' => ['label' => 'Dapur / Bar', 'abilities' => ['kitchen', 'production', 'attendance.self']],
        'warehouse' => ['label' => 'Gudang', 'abilities' => ['dashboard', 'inventory', 'purchasing', 'production', 'attendance.self']],
        'staff' => ['label' => 'Staf', 'abilities' => ['attendance.self']],
    ],

    /*
    | Paket langganan SaaS. Modul yang tidak ada di paket otomatis tersembunyi
    | & terkunci di server. Harga per bulan (Rupiah); 12 bulan = bayar 10 bulan.
    */
    'plans' => [
        'basic' => [
            'label' => 'Basic', 'price' => 99000, 'max_branches' => 1, 'max_users' => 5,
            'modules' => ['pos', 'kitchen', 'inventory', 'reports', 'payment_gateway'],
            'tagline' => 'Kasir, dapur, stok, resep & QRIS otomatis untuk 1 outlet',
        ],
        'pro' => [
            'label' => 'Pro', 'price' => 249000, 'max_branches' => 3, 'max_users' => 25,
            'modules' => ['pos', 'kitchen', 'inventory', 'reports', 'payment_gateway', 'online_order', 'hr', 'payroll', 'whatsapp'],
            'tagline' => '+ QR meja, Order Ahead, notifikasi WhatsApp, karyawan, absensi GPS & payroll',
        ],
        'enterprise' => [
            'label' => 'Enterprise', 'price' => 599000, 'max_branches' => 20, 'max_users' => 200,
            'modules' => ['pos', 'kitchen', 'inventory', 'reports', 'payment_gateway', 'online_order', 'hr', 'payroll', 'whatsapp'],
            'tagline' => 'Semua fitur untuk grup usaha multi-cabang',
        ],
    ],

    'modules' => [
        'pos' => 'Kasir & pesanan', 'kitchen' => 'Layar dapur (KDS)', 'inventory' => 'Stok, resep, produksi & pembelian',
        'reports' => 'Laporan', 'online_order' => 'QR meja & Order Ahead', 'hr' => 'Karyawan, jadwal & absensi GPS', 'payroll' => 'Payroll & slip gaji',
        'payment_gateway' => 'Payment gateway (QRIS & Virtual Account otomatis)', 'whatsapp' => 'Notifikasi WhatsApp',
    ],

    // Kemampuan (ability) → modul paket. Kemampuan yang tidak tercantum selalu tersedia.
    'ability_modules' => [
        'kitchen' => 'kitchen', 'inventory' => 'inventory', 'purchasing' => 'inventory', 'recipes' => 'inventory',
        'production' => 'inventory', 'employees' => 'hr', 'schedule' => 'hr', 'attendance' => 'hr', 'payroll' => 'payroll',
        'reports' => 'reports', 'finance' => 'reports',
    ],

    /*
    | Payment gateway. Kredensial diisi pemilik usaha di Pengaturan → Integrasi (dana masuk ke akun gateway usaha itu sendiri).
    */
    'gateways' => [
        'midtrans' => ['label' => 'Midtrans', 'channels' => ['qris', 'va_bca', 'va_bni', 'va_bri', 'va_permata'],
            'fields' => ['server_key' => 'Server Key', 'client_key' => 'Client Key (opsional)'],
            'help' => 'Dashboard Midtrans → Settings → Access Keys. Gunakan kunci Sandbox untuk uji coba.'],
        'xendit' => ['label' => 'Xendit', 'channels' => ['qris', 'va_bca', 'va_bni', 'va_bri', 'va_mandiri', 'va_permata'],
            'fields' => ['secret_key' => 'Secret API Key', 'callback_token' => 'Webhook Verification Token'],
            'help' => 'Dashboard Xendit → Settings → API Keys (izin Write untuk QR Codes & Virtual Accounts) dan Settings → Webhooks.'],
    ],
    'gateway_channels' => [
        'qris' => 'QRIS', 'va_bca' => 'VA BCA', 'va_bni' => 'VA BNI', 'va_bri' => 'VA BRI', 'va_mandiri' => 'VA Mandiri', 'va_permata' => 'VA Permata',
    ],
    'gateway_expiry_minutes' => ['qris' => 15, 'va' => 1440],

    /*
    | WhatsApp gateway & templat pesan. Placeholder ditulis {nama}.
    */
    'whatsapp_providers' => [
        'fonnte' => ['label' => 'Fonnte', 'fields' => ['token' => 'Token perangkat'], 'help' => 'Dashboard Fonnte → Device → salin Token. Pastikan perangkat berstatus connect.'],
        'wablas' => ['label' => 'Wablas', 'fields' => ['base_url' => 'Alamat server (mis. https://tegal.wablas.com)', 'token' => 'Token', 'secret_key' => 'Secret key (opsional)'],
            'help' => 'Dashboard Wablas → Device → Settings. Alamat server sesuai domain akun Anda.'],
    ],
    'wa_events' => [
        'order_paid' => ['label' => 'Pembayaran diterima (ke pelanggan)', 'placeholders' => '{nama} {nomor} {total} {usaha} {cabang} {link_nota} {link_lacak}',
            'template' => "Terima kasih {nama}! Pembayaran pesanan {nomor} di {usaha} sebesar {total} sudah kami terima.
Nota digital: {link_nota}
Lacak pesanan: {link_lacak}"],
        'order_ready' => ['label' => 'Pesanan siap (ke pelanggan)', 'placeholders' => '{nama} {nomor} {usaha} {cabang} {kode_ambil} {link_lacak}',
            'template' => "Halo {nama}, pesanan {nomor} di {usaha} sudah SIAP. {kode_ambil}
Lacak: {link_lacak}"],
        'closing_summary' => ['label' => 'Ringkasan closing kasir (ke pemilik)', 'placeholders' => '{cabang} {tanggal} {kasir} {jumlah_pesanan} {tunai} {non_tunai} {selisih} {usaha}',
            'template' => "Closing {cabang} {tanggal} oleh {kasir}:
{jumlah_pesanan} pesanan
Tunai: {tunai}
Non-tunai: {non_tunai}
Selisih kas: {selisih}"],
    ],
    'wa_platform_events' => [
        'invoice_created' => ['label' => 'Tagihan langganan dibuat (ke pemilik usaha)', 'placeholders' => '{pemilik} {usaha} {nomor_tagihan} {jumlah} {paket} {durasi} {jatuh_tempo} {bank} {rekening} {atas_nama} {link}',
            'template' => "Halo {pemilik}, tagihan langganan {usaha} {nomor_tagihan}: {jumlah} untuk paket {paket} ({durasi}).
Bayar sebelum {jatuh_tempo} ke {bank} {rekening} a.n. {atas_nama}, lalu unggah bukti di {link}"],
        'invoice_paid' => ['label' => 'Pembayaran langganan diterima', 'placeholders' => '{pemilik} {usaha} {nomor_tagihan} {paket} {berlaku_sampai}',
            'template' => "Terima kasih {pemilik}! Pembayaran {nomor_tagihan} diterima. Langganan {usaha} paket {paket} aktif sampai {berlaku_sampai}."],
    ],

    'billing_months' => [1 => '1 bulan', 3 => '3 bulan', 6 => '6 bulan', 12 => '12 bulan (hemat 2 bulan)'],

    // Nilai bawaan pengaturan platform (bisa diubah Super Admin)
    'platform_defaults' => [
        'trial_days' => 14,
        'grace_days' => 3,
        'bank_name' => 'Nama Bank',
        'bank_account' => '0000000000',
        'bank_holder' => 'Nama Pemilik Rekening',
        'support_whatsapp' => '080000000000',
        'registration_open' => '1',
        'wa_enabled' => '0',
        'wa_provider' => 'fonnte',
        'wa_base_url' => '',
        'wa_event_invoice_created' => '1',
        'wa_event_invoice_paid' => '1',
        'wa_template_invoice_created' => '',
        'wa_template_invoice_paid' => '',
    ],

    'payment_methods' => [
        'cash' => 'Tunai',
        'qris' => 'QRIS',
        'transfer' => 'Transfer / VA',
        'ewallet' => 'E-wallet',
    ],

    // Pecahan Rupiah untuk closing kasir
    'denominations' => [100000, 50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100],

    // Batas persetujuan (approval)
    'approval' => [
        'waste_value' => 50000,          // waste di atas nilai ini wajib disetujui manajer
        'discount_percent' => 20,        // diskon di atas persen ini wajib peran dengan orders.void
        'closing_difference' => 0,       // closing dengan selisih != 0 → menunggu persetujuan
    ],

    'units' => [
        'gram', 'kilogram', 'ons', 'mililiter', 'liter', 'pcs', 'butir', 'buah', 'porsi', 'tusuk', 'potong',
        'ekor', 'ikat', 'pack', 'sachet', 'renteng', 'lusin', 'kodi', 'rim', 'tray', 'krat', 'botol', 'kaleng',
        'cup', 'box', 'karton', 'karung', 'jeriken',
    ],

    'theme_presets' => [
        'terracotta' => ['primary' => '#C2410C', 'accent' => '#15803D', 'background' => '#FAF7F2', 'surface' => '#FFFFFF', 'text' => '#1C1917', 'border' => '#E7E0D6'],
        'daun' => ['primary' => '#15803D', 'accent' => '#CA8A04', 'background' => '#F6F8F4', 'surface' => '#FFFFFF', 'text' => '#14201A', 'border' => '#DCE5DA'],
        'kopi' => ['primary' => '#7C4A2D', 'accent' => '#B45309', 'background' => '#F7F3EF', 'surface' => '#FFFFFF', 'text' => '#2A1D16', 'border' => '#E5DAD0'],
        'laut' => ['primary' => '#0E7490', 'accent' => '#EA580C', 'background' => '#F3F7F8', 'surface' => '#FFFFFF', 'text' => '#0F1F24', 'border' => '#D6E3E7'],
        'cabai' => ['primary' => '#B91C1C', 'accent' => '#0F766E', 'background' => '#FBF6F5', 'surface' => '#FFFFFF', 'text' => '#1F1515', 'border' => '#EBDCDA'],
    ],
];
