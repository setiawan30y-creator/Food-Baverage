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
 * Xendit: QR Codes API (QRIS dinamis, versi 2022-07-31) & Fixed Virtual Account (closed, sekali pakai).
 * Webhook diverifikasi dengan header X-CALLBACK-TOKEN.
 * @2026-Alifiandra
 */
class XenditGateway implements PaymentGateway
{
    const BASE = 'https://api.xendit.co';

    public function __construct(protected TenantIntegration $integration) {}

    protected function http(): PendingRequest
    {
        return Http::withBasicAuth((string) $this->integration->cred('secret_key'), '')->acceptJson()->asJson()->timeout(20);
    }

    public function charge(GatewayCharge $charge, Order $order): array
    {
        if ($charge->channel === 'qris') {
            $expires = now()->addMinutes(config('fnb.gateway_expiry_minutes.qris'));
            $res = $this->http()->withHeaders(['api-version' => '2022-07-31'])->post(self::BASE.'/qr_codes', [
                'reference_id' => $charge->reference, 'type' => 'DYNAMIC', 'currency' => 'IDR',
                'amount' => (int) round($charge->amount), 'expires_at' => $expires->toIso8601String(),
            ]);
            $j = $res->json() ?? [];
            if (! $res->successful() || empty($j['qr_string'])) {
                throw new \RuntimeException('Xendit menolak tagihan QRIS: '.($j['message'] ?? 'HTTP '.$res->status()));
            }

            return ['provider_ref' => $j['id'] ?? null, 'qr_string' => $j['qr_string'], 'qr_url' => null, 'va_number' => null,
                'expires_at' => isset($j['expires_at']) ? Carbon::parse($j['expires_at']) : $expires, 'payload' => $j];
        }

        $expires = now()->addMinutes(config('fnb.gateway_expiry_minutes.va'));
        $res = $this->http()->post(self::BASE.'/callback_virtual_accounts', [
            'external_id' => $charge->reference, 'bank_code' => strtoupper(substr($charge->channel, 3)),
            'name' => mb_substr(preg_replace('/[^A-Za-z ]/', '', $order->customer_name ?: 'Pelanggan') ?: 'Pelanggan', 0, 50),
            'expected_amount' => (int) round($charge->amount), 'is_closed' => true, 'is_single_use' => true,
            'expiration_date' => $expires->toIso8601String(),
        ]);
        $j = $res->json() ?? [];
        if (! $res->successful() || empty($j['account_number'])) {
            throw new \RuntimeException('Xendit menolak Virtual Account: '.($j['message'] ?? 'HTTP '.$res->status()));
        }

        return ['provider_ref' => $j['id'] ?? null, 'qr_string' => null, 'qr_url' => null, 'va_number' => $j['account_number'],
            'expires_at' => isset($j['expiration_date']) ? Carbon::parse($j['expiration_date']) : $expires, 'payload' => $j];
    }

    public function parseNotification(Request $request): ?array
    {
        $token = (string) $this->integration->cred('callback_token');
        if ($token === '' || ! hash_equals($token, (string) $request->header('x-callback-token'))) {
            throw new \RuntimeException('Token webhook Xendit tidak valid.');
        }
        $j = $request->json()->all() ?: $request->all();

        // QRIS (QR Codes API 2022-07-31)
        if (isset($j['event']) && str_starts_with((string) $j['event'], 'qr.')) {
            $d = $j['data'] ?? [];
            $status = ($d['status'] ?? '') === 'SUCCEEDED' ? 'paid' : 'pending';

            return ['reference' => (string) ($d['reference_id'] ?? ''), 'status' => $status, 'amount' => isset($d['amount']) ? (float) $d['amount'] : null,
                'provider_ref' => $d['qr_id'] ?? null, 'payload' => $j];
        }
        // Virtual Account: notifikasi pembayaran memiliki payment_id
        if (isset($j['external_id'], $j['payment_id'])) {
            return ['reference' => (string) $j['external_id'], 'status' => 'paid', 'amount' => (float) ($j['amount'] ?? 0),
                'provider_ref' => $j['callback_virtual_account_id'] ?? null, 'payload' => $j];
        }

        return null; // notifikasi VA dibuat/diperbarui, dsb.
    }

    public function check(GatewayCharge $charge): ?string
    {
        if ($charge->channel !== 'qris' || ! $charge->provider_ref) {
            return null; // status VA mengandalkan webhook
        }
        $res = $this->http()->withHeaders(['api-version' => '2022-07-31'])->get(self::BASE.'/qr_codes/'.rawurlencode($charge->provider_ref).'/payments');
        $rows = $res->json('data') ?? [];

        return collect($rows)->contains(fn ($p) => ($p['status'] ?? '') === 'SUCCEEDED') ? 'paid' : null;
    }

    public function test(): string
    {
        if (! $this->integration->cred('secret_key')) {
            throw new \RuntimeException('Secret API Key belum diisi.');
        }
        $res = $this->http()->get(self::BASE.'/balance');
        if ($res->status() === 401 || $res->status() === 403) {
            throw new \RuntimeException('Secret Key ditolak Xendit ('.$res->status().'). Periksa kunci & izinnya.');
        }
        if (! $res->successful()) {
            throw new \RuntimeException('Xendit merespons '.$res->status().': '.($res->json('message') ?? ''));
        }
        $mode = str_starts_with((string) $this->integration->cred('secret_key'), 'xnd_development') ? 'mode Test' : 'mode Live';

        return 'Terhubung ke Xendit ('.$mode.'). Saldo: '.rupiah((float) $res->json('balance', 0)).'.';
    }
}
