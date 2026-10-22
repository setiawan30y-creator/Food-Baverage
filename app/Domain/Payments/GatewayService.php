<?php

namespace App\Domain\Payments;

use App\Domain\Finance\AccountService;
use App\Domain\Ordering\OrderService;
use App\Domain\Payments\Gateways\MidtransGateway;
use App\Domain\Payments\Gateways\PaymentGateway;
use App\Domain\Payments\Gateways\XenditGateway;
use App\Domain\Shared\Audit;
use App\Models\Branch;
use App\Models\GatewayCharge;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pembayaran online via payment gateway milik usaha (Midtrans / Xendit).
 * Pesanan HANYA ditandai lunas setelah gateway mengonfirmasi (webhook terverifikasi atau cek status server-ke-server).
 * @2026-Alifiandra
 */
class GatewayService
{
    public function __construct(protected OrderService $orders) {}

    public static function driver(TenantIntegration $integration): PaymentGateway
    {
        return match ($integration->provider) {
            'midtrans' => new MidtransGateway($integration),
            'xendit' => new XenditGateway($integration),
            default => throw new \DomainException('Payment gateway tidak dikenal.'),
        };
    }

    /** Integrasi pembayaran aktif untuk tenant saat ini (memperhatikan paket). */
    public function active(?Tenant $tenant = null): ?TenantIntegration
    {
        $tenant ??= tenancy()->tenant();
        if (! $tenant || ! $tenant->hasModule('payment_gateway')) {
            return null;
        }

        return TenantIntegration::active('payment', $tenant->id);
    }

    /** Kanal yang diaktifkan pemilik (urutan sesuai konfigurasi). */
    public function channels(?TenantIntegration $integration): array
    {
        if (! $integration) {
            return [];
        }
        $supported = config("fnb.gateways.{$integration->provider}.channels", []);
        $enabled = $integration->setting('channels', ['qris']);

        return array_values(array_intersect($supported, $enabled));
    }

    public function channelOptions(?TenantIntegration $integration): array
    {
        return collect($this->channels($integration))->map(fn ($c) => ['id' => $c, 'label' => config("fnb.gateway_channels.{$c}")])->all();
    }

    /** Buat (atau pakai ulang) tagihan QRIS/VA untuk pesanan. */
    public function createCharge(Order $order, string $channel): GatewayCharge
    {
        $integration = $this->active(Tenant::find($order->tenant_id));
        if (! $integration) {
            throw new \DomainException('Payment gateway belum diaktifkan. Atur di Pengaturan → Integrasi.');
        }
        if (! in_array($channel, $this->channels($integration), true)) {
            throw new \DomainException('Metode pembayaran online tidak tersedia.');
        }
        if ($order->payment_status === 'paid') {
            throw new \DomainException('Pesanan sudah lunas.');
        }
        if ($order->status === 'cancelled') {
            throw new \DomainException('Pesanan sudah dibatalkan.');
        }
        if ((float) $order->total < 1000) {
            throw new \DomainException('Minimal pembayaran online Rp 1.000.');
        }

        $existing = GatewayCharge::withoutGlobalScopes()->where('order_id', $order->id)->where('channel', $channel)
            ->where('provider', $integration->provider)->where('status', 'pending')->where('amount', $order->total)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()->addMinute()))->latest()->first();
        if ($existing) {
            return $existing;
        }

        $charge = GatewayCharge::withoutGlobalScopes()->create([
            'tenant_id' => $order->tenant_id, 'branch_id' => $order->branch_id, 'order_id' => $order->id,
            'provider' => $integration->provider, 'channel' => $channel,
            'reference' => Str::limit(preg_replace('/[^A-Za-z0-9-]/', '', $order->number), 30, '').'-'.strtoupper(Str::random(6)),
            'amount' => $order->total, 'status' => 'pending',
        ]);

        try {
            $r = static::driver($integration)->charge($charge, $order);
        } catch (\Throwable $e) {
            $charge->update(['status' => 'failed', 'payload' => ['error' => $e->getMessage()]]);
            $integration->update(['last_error' => Str::limit($e->getMessage(), 250)]);
            Log::warning('Gateway charge gagal', ['charge' => $charge->id, 'error' => $e->getMessage()]);
            throw new \DomainException($e instanceof \RuntimeException ? $e->getMessage() : 'Gagal menghubungi payment gateway. Coba lagi atau gunakan metode lain.');
        }

        $payload = $r['payload'];
        if (! empty($r['qr_url'])) {
            $payload['_qr_url'] = $r['qr_url'];
        }
        $charge->update([
            'provider_ref' => $r['provider_ref'], 'qr_string' => $r['qr_string'], 'va_number' => $r['va_number'],
            'expires_at' => $r['expires_at'], 'payload' => $payload,
        ]);
        Audit::log('payment.gateway_charge', $order, null, ['reference' => $charge->reference, 'channel' => $channel, 'amount' => (float) $charge->amount]);

        return $charge->fresh();
    }

    /** Proses webhook. Mengembalikan pesan singkat untuk log/respons. */
    public function handleWebhook(TenantIntegration $integration, Request $request): string
    {
        $n = static::driver($integration)->parseNotification($request); // melempar bila tidak valid
        if (! $n) {
            return 'ignored';
        }
        $charge = GatewayCharge::withoutGlobalScopes()->where('tenant_id', $integration->tenant_id)->where('reference', $n['reference'])->first();
        if (! $charge) {
            return 'unknown reference';
        }
        $charge->update(['payload' => array_merge($charge->payload ?? [], ['last_notification' => $n['payload']])]);

        return $this->apply($charge, $n['status'], $n['amount'], $n['provider_ref']);
    }

    /** Cek status ke gateway bila masih menunggu (cadangan saat webhook belum bisa masuk, mis. di localhost). */
    public function refresh(GatewayCharge $charge): GatewayCharge
    {
        if ($charge->status !== 'pending') {
            return $charge;
        }
        $integration = TenantIntegration::withoutGlobalScopes()->where('tenant_id', $charge->tenant_id)->where('type', 'payment')->first();
        if ($integration) {
            try {
                $status = static::driver($integration)->check($charge);
                if ($status && $status !== 'pending') {
                    $this->apply($charge, $status, null, null);
                }
            } catch (\Throwable $e) {
                Log::info('Cek status gateway gagal', ['charge' => $charge->id, 'error' => $e->getMessage()]);
            }
        }
        $charge->refresh();
        if ($charge->status === 'pending' && $charge->expires_at && $charge->expires_at->lt(now()->subMinutes(5))) {
            $charge->update(['status' => 'expired']);
        }

        return $charge;
    }

    /** Terapkan status dari gateway. Idempoten: pembayaran ganda tidak tercatat dua kali. */
    public function apply(GatewayCharge $charge, string $status, ?float $amount, ?string $providerRef): string
    {
        if ($status !== 'paid') {
            if ($charge->status === 'pending' && in_array($status, ['expired', 'failed'], true)) {
                $charge->update(['status' => $status]);
            }

            return $status;
        }

        return DB::transaction(function () use ($charge, $amount, $providerRef) {
            $charge = GatewayCharge::withoutGlobalScopes()->whereKey($charge->id)->lockForUpdate()->first();
            if ($charge->status === 'paid') {
                return 'already paid';
            }
            if ($amount !== null && $amount + 0.5 < (float) $charge->amount) {
                $charge->update(['status' => 'failed', 'payload' => array_merge($charge->payload ?? [], ['error' => 'Nominal dibayar kurang'])]);
                Audit::log('payment.gateway_amount_mismatch', $charge, null, ['expected' => (float) $charge->amount, 'paid' => $amount]);

                return 'amount mismatch';
            }

            $tenant = Tenant::findOrFail($charge->tenant_id);
            tenancy()->setTenant($tenant);
            tenancy()->setBranch(Branch::withoutGlobalScopes()->findOrFail($charge->branch_id));
            $integration = TenantIntegration::withoutGlobalScopes()->where('tenant_id', $charge->tenant_id)->where('type', 'payment')->first();
            $fee = $this->fee($integration, $charge);
            $method = $charge->channel === 'qris' ? 'qris' : 'transfer';
            $order = Order::withoutGlobalScopes()->findOrFail($charge->order_id);

            $charge->update(['status' => 'paid', 'paid_at' => now(), 'fee' => $fee, 'provider_ref' => $providerRef ?: $charge->provider_ref]);

            if ($order->payment_status === 'paid' || $order->status === 'cancelled') {
                // Pelanggan membayar setelah pesanan lunas/batal → perlu pengembalian dana manual
                Audit::log('payment.gateway_double_payment', $order, null, ['reference' => $charge->reference, 'amount' => (float) $charge->amount],
                    'Dana masuk lewat gateway namun pesanan sudah '.($order->status === 'cancelled' ? 'dibatalkan' : 'lunas').'. Lakukan refund dari dashboard gateway.');

                return 'paid (order already settled)';
            }

            $accountId = $integration?->setting($method === 'qris' ? 'account_qris_id' : 'account_va_id');
            $payment = $this->orders->pay($order, $method, (float) $order->total, $charge->channelLabel().' '.$charge->reference, null,
                'gw-'.$charge->reference, $accountId ? (int) $accountId : null, false);
            $payment->update(['provider' => $charge->provider, 'provider_fee' => $fee]);
            $charge->update(['payment_id' => $payment->id]);

            if ($fee > 0 && $payment->payment_account_id) {
                app(AccountService::class)->record(PaymentAccount::findOrFail($payment->payment_account_id), 'out', 'fee', $fee, today()->toDateString(),
                    'Biaya '.config("fnb.gateways.{$charge->provider}.label").' '.$charge->channelLabel().' · '.$order->number);
            }
            Audit::log('payment.gateway_paid', $order, null, ['reference' => $charge->reference, 'amount' => (float) $charge->amount, 'fee' => $fee]);

            return 'paid';
        });
    }

    /** Perkiraan biaya (MDR) sesuai pengaturan pemilik. */
    public function fee(?TenantIntegration $integration, GatewayCharge $charge): float
    {
        if ($charge->channel === 'qris') {
            $pct = (float) ($integration?->setting('fee_qris_percent', 0.7) ?? 0.7);

            return round((float) $charge->amount * $pct / 100);
        }

        return (float) ($integration?->setting('fee_va_flat', 4000) ?? 4000);
    }
}
