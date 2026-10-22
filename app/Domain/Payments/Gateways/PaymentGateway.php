<?php

namespace App\Domain\Payments\Gateways;

use App\Models\GatewayCharge;
use App\Models\Order;
use Illuminate\Http\Request;

/** Kontrak driver payment gateway. @2026-Alifiandra */
interface PaymentGateway
{
    /**
     * Buat tagihan di gateway.
     *
     * @return array{provider_ref:?string, qr_string:?string, qr_url:?string, va_number:?string, expires_at:?\DateTimeInterface, payload:array}
     */
    public function charge(GatewayCharge $charge, Order $order): array;

    /**
     * Baca & verifikasi notifikasi (webhook). Lempar \RuntimeException bila tanda tangan/token tidak valid.
     * Kembalikan null bila notifikasi bukan tentang pembayaran (diabaikan).
     *
     * @return array{reference:string, status:string, amount:?float, provider_ref:?string, payload:array}|null
     */
    public function parseNotification(Request $request): ?array;

    /** Cek status langsung ke gateway (cadangan bila webhook belum masuk). null = tidak didukung/tidak diketahui. */
    public function check(GatewayCharge $charge): ?string;

    /** Uji kunci API. Lempar \RuntimeException dengan pesan yang mudah dipahami bila gagal. */
    public function test(): string;
}
