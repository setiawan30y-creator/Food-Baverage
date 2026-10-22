<?php

/**
 * Helper global aplikasi.
 * @2026-Alifiandra
 */

use App\Support\Tenancy;

if (! function_exists('tenancy')) {
    function tenancy(): Tenancy
    {
        return app(Tenancy::class);
    }
}

if (! function_exists('rupiah')) {
    function rupiah($value, bool $prefix = true): string
    {
        $formatted = number_format((float) $value, 0, ',', '.');

        return $prefix ? 'Rp '.$formatted : $formatted;
    }
}

if (! function_exists('qty')) {
    function qty($value, int $decimals = 2): string
    {
        $v = (float) $value;
        $d = floor($v) == $v ? 0 : $decimals;

        return number_format($v, $d, ',', '.');
    }
}

if (! function_exists('status_badge')) {
    function status_badge(?string $status): string
    {
        $map = [
            'new' => ['Baru', 'info'], 'accepted' => ['Diterima', 'info'], 'preparing' => ['Diproses', 'warn'],
            'ready' => ['Siap', 'ok'], 'completed' => ['Selesai', 'muted'], 'cancelled' => ['Batal', 'danger'],
            'served' => ['Disajikan', 'muted'], 'unpaid' => ['Belum bayar', 'warn'], 'pending' => ['Menunggu', 'warn'],
            'paid' => ['Lunas', 'ok'], 'refunded' => ['Refund', 'danger'], 'approved' => ['Disetujui', 'ok'],
            'rejected' => ['Ditolak', 'danger'], 'submitted' => ['Diajukan', 'warn'], 'draft' => ['Draft', 'muted'],
            'calculated' => ['Dihitung', 'info'], 'received' => ['Diterima', 'ok'], 'posted' => ['Diposting', 'ok'],
            'open' => ['Buka', 'ok'], 'closed' => ['Tutup', 'muted'], 'final' => ['Final', 'ok'],
            'reopened' => ['Dibuka ulang', 'warn'], 'active' => ['Aktif', 'ok'], 'inactive' => ['Nonaktif', 'muted'],
            'available' => ['Tersedia', 'ok'], 'occupied' => ['Terisi', 'warn'], 'awaiting_payment' => ['Menunggu bayar', 'danger'],
            'success' => ['Sukses', 'ok'], 'failed' => ['Gagal', 'danger'], 'partial' => ['Sebagian', 'warn'],
            'expired' => ['Kedaluwarsa', 'muted'], 'sent' => ['Terkirim', 'ok'], 'queued' => ['Antre', 'warn'],
        ];
        [$label, $tone] = $map[$status] ?? [ucfirst((string) $status), 'muted'];

        return '<span class="badge badge-'.$tone.'">'.e($label).'</span>';
    }
}
