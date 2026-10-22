<?php

namespace App\Domain\Payments\Gateways;

use App\Models\GatewayCharge;
use App\Models\Order;
use App\Models\TenantIntegration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Midtrans Core API: QRIS (acquirer GoPay) & Virtual Account (BCA, BNI, BRI, Permata).
 * Notifikasi diverifikasi dengan signature_key = SHA512(order_id + status_code + gross_amount + server_key).
 * @2026-Alifiandra
 */
class MidtransGateway implements PaymentGateway
{
    public function __construct(protected TenantIntegration $integration) {}

    protected function base(): string
    {
        return $this->integration->setting('sandbox', true) ? 'https://api.sandbox.midtrans.com' : 'https://api.midtrans.com';
    }

    protected function http(): PendingRequest
    {
        return Http::withBasicAuth((string) $this->integration->cred('server_key'), '')
            ->acceptJson()->asJson()->timeout(20);
    }

    public function charge(GatewayCharge $charge, Order $order): array
    {
        $minutes = $charge->channel === 'qris' ? config('fnb.gateway_expiry_minutes.qris') : config('fnb.gateway_expiry_minutes.va');
        $body = [
            'transaction_details' => ['order_id' => $charge->reference, 'gross_amount' => (int) round($charge->amount)],
            'custom_expiry' => ['expiry_duration' => $minutes, 'unit' => 'minute'],
            'customer_details' => array_filter(['first_name' => $order->customer_name ?: 'Pelanggan', 'phone' => $order->customer_phone]),
            'item_details' => [['id' => 'ORDER', 'price' => (int) round($charge->amount), 'quantity' => 1, 'name' => mb_substr('Pesanan '.$order->number, 0, 50)]],
        ];
        if ($charge->channel === 'qris') {
            $body += ['payment_type' => 'qris', 'qris' => ['acquirer' => 'gopay']];
        } else {
            $body += ['payment_type' => 'bank_transfer', 'bank_transfer' => ['bank' => substr($charge->channel, 3)]];
        }

        $res = $this->http()->post($this->base().'/v2/charge', $body);
        $j = $res->json() ?? [];
        $code = (string) ($j['status_code'] ?? $res->status());
        if (! in_array($code, ['200', '201'], true)) {
            throw new \RuntimeException('Midtrans menolak tagihan: '.$this->message($j, $res->status()));
        }

        $qrUrl = collect($j['actions'] ?? [])->firstWhere('name', 'generate-qr-code')['url'] ?? null;
        $va = $j['va_numbers'][0]['va_number'] ?? ($j['permata_va_number'] ?? null);

        return [
            'provider_ref' => $j['transaction_id'] ?? null,
            'qr_string' => $j['qr_string'] ?? null,
            'qr_url' => $qrUrl,
            'va_number' => $va,
            'expires_at' => isset($j['expiry_time']) ? Carbon::parse($j['expiry_time'], 'Asia/Jakarta') : now()->addMinutes($minutes),
            'payload' => $j,
        ];
    }

    public function parseNotification(Request $request): ?array
    {
        $j = $request->json()->all() ?: $request->all();
        if (! isset($j['order_id'], $j['status_code'], $j['gross_amount'], $j['signature_key'])) {
            throw new \RuntimeException('Notifikasi Midtrans tidak lengkap.');
        }
        $expected = hash('sha512', $j['order_id'].$j['status_code'].$j['gross_amount'].$this->integration->cred('server_key'));
        if (! hash_equals($expected, (string) $j['signature_key'])) {
            throw new \RuntimeException('Tanda tangan notifikasi Midtrans tidak valid.');
        }

        return [
            'reference' => (string) $j['order_id'],
            'status' => $this->map((string) ($j['transaction_status'] ?? ''), (string) ($j['fraud_status'] ?? 'accept')),
            'amount' => (float) $j['gross_amount'],
            'provider_ref' => $j['transaction_id'] ?? null,
            'payload' => $j,
        ];
    }

    public function check(GatewayCharge $charge): ?string
    {
        $res = $this->http()->get($this->base().'/v2/'.rawurlencode($charge->reference).'/status');
        $j = $res->json() ?? [];
        if (! isset($j['transaction_status'])) {
            return null;
        }

        return $this->map((string) $j['transaction_status'], (string) ($j['fraud_status'] ?? 'accept'));
    }

    public function test(): string
    {
        if (! $this->integration->cred('server_key')) {
            throw new \RuntimeException('Server Key belum diisi.');
        }
        $res = $this->http()->get($this->base().'/v2/uji-koneksi-'.now()->timestamp.'/status');
        $code = (string) ($res->json('status_code') ?? $res->status());
        if ($res->status() === 401 || $code === '401') {
            throw new \RuntimeException('Server Key ditolak Midtrans (401). Periksa kunci & mode Sandbox/Production.');
        }
        if ($res->serverError()) {
            throw new \RuntimeException('Server Midtrans sedang bermasalah ('.$res->status().').');
        }

        return 'Terhubung ke Midtrans '.($this->integration->setting('sandbox', true) ? 'Sandbox' : 'Production').'.';
    }

    protected function map(string $status, string $fraud): string
    {
        return match ($status) {
            'settlement' => 'paid',
            'capture' => $fraud === 'accept' ? 'paid' : 'pending',
            'expire' => 'expired',
            'deny', 'cancel', 'failure' => 'failed',
            default => 'pending',
        };
    }

    protected function message(array $j, int $http): string
    {
        $msg = $j['status_message'] ?? ($j['error_messages'][0] ?? null);

        return $msg ? (string) $msg : 'HTTP '.$http;
    }
}
