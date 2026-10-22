<?php

/**
 * Konfigurasi ALMARA Restaurant & Beverage OS.
 * @2026-Alifiandra
 */

return [
    'product_name' => 'ALMARA Restaurant & Beverage OS',
    'version' => '1.1.0',
    'signature' => '@2026-Alifiandra',

    /*
    | Peran & kemampuan. Hak akses diberlakukan di backend (middleware "ability:...").
    */
    'roles' => [
        'owner' => ['label' => 'Pemilik', 'abilities' => ['*']],
        'manager' => ['label' => 'Manajer', 'abilities' => [
            'dashboard', 'pos', 'orders', 'orders.void', 'kitchen', 'tables', 'menu', 'menu.price', 'inventory',
            'inventory.approve', 'purchasing', 'recipes', 'production', 'closing', 'closing.approve',
            'employees', 'schedule', 'attendance.self', 'attendance.manage', 'payroll', 'reports', 'audit',
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
