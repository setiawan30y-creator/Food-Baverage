<?php

namespace App\Domain\WhatsApp;

use App\Models\CashClosing;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Models\WaMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Notifikasi WhatsApp: per usaha (nota, pesanan siap, ringkasan closing) & platform (tagihan langganan).
 * Pesan dikirim setelah respons halaman selesai, sehingga kasir tidak menunggu; kegagalan hanya dicatat di log pesan.
 * @2026-Alifiandra
 */
class WhatsAppService
{
    // ---------- konfigurasi ----------

    public function tenantSender(int $tenantId): ?WhatsAppSender
    {
        $tenant = Tenant::find($tenantId);
        if (! $tenant || ! $tenant->hasModule('whatsapp')) {
            return null;
        }
        $i = TenantIntegration::active('whatsapp', $tenantId);

        return $i ? new WhatsAppSender(['provider' => $i->provider] + ($i->credentials ?? [])) : null;
    }

    public function platformSender(): ?WhatsAppSender
    {
        if (PlatformSetting::get('wa_enabled') !== '1' || ! PlatformSetting::get('wa_provider')) {
            return null;
        }

        return new WhatsAppSender([
            'provider' => PlatformSetting::get('wa_provider'),
            'token' => self::decrypt(PlatformSetting::get('wa_token')),
            'secret_key' => self::decrypt(PlatformSetting::get('wa_secret_key')),
            'base_url' => PlatformSetting::get('wa_base_url'),
        ]);
    }

    public static function decrypt(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function render(string $template, array $vars): string
    {
        $out = strtr($template, collect($vars)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => (string) $v])->all());

        return trim(preg_replace("/[ \t]+\n/", "\n", preg_replace('/\{[a-z_]+\}/', '', $out)));
    }

    // ---------- pengiriman ----------

    /** Kirim sekarang & catat di log. */
    public function sendNow(?WhatsAppSender $sender, ?int $tenantId, string $event, ?string $phone, string $message, ?Model $subject = null): ?WaMessage
    {
        $to = WhatsAppSender::normalize($phone);
        if (! $sender || ! $to || trim($message) === '') {
            return null;
        }
        $log = WaMessage::create([
            'tenant_id' => $tenantId, 'provider' => $sender->provider(), 'event' => $event, 'to' => $to, 'message' => $message,
            'status' => 'queued', 'subject_type' => $subject ? $subject::class : null, 'subject_id' => $subject?->getKey(),
        ]);
        try {
            $res = $sender->send($to, $message);
            $log->update(['status' => 'sent', 'response' => $res, 'sent_at' => now()]);
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 250)]);
            Log::warning('WhatsApp gagal terkirim', ['id' => $log->id, 'error' => $e->getMessage()]);
        }

        return $log;
    }

    /** Jalankan setelah transaksi DB & respons selesai, agar tidak memperlambat kasir dan tidak terkirim bila transaksi batal. */
    protected function later(callable $fn): void
    {
        DB::afterCommit(function () use ($fn) {
            dispatch(function () use ($fn) {
                try {
                    $fn();
                } catch (\Throwable $e) {
                    Log::warning('Notifikasi WhatsApp gagal diproses', ['error' => $e->getMessage()]);
                }
            })->afterResponse();
        });
    }

    protected function tenantEvent(int $tenantId, string $event): ?array
    {
        $i = TenantIntegration::active('whatsapp', $tenantId);
        if (! $i || ! data_get($i->settings, "events.{$event}.enabled", in_array($event, ['order_paid', 'order_ready'], true))) {
            return null;
        }

        return ['integration' => $i, 'template' => data_get($i->settings, "events.{$event}.template") ?: config("fnb.wa_events.{$event}.template")];
    }

    // ---------- event usaha ----------

    public function orderPaid(Order $order): void
    {
        if (! $order->customer_phone) {
            return;
        }
        $orderId = $order->id;
        $this->later(function () use ($orderId) {
            $order = Order::withoutGlobalScopes()->with(['tenant', 'branch'])->find($orderId);
            if (! $order || ! ($cfg = $this->tenantEvent($order->tenant_id, 'order_paid'))) {
                return;
            }
            $msg = self::render($cfg['template'], $this->orderVars($order));
            $this->sendNow($this->tenantSender($order->tenant_id), $order->tenant_id, 'order_paid', $order->customer_phone, $msg, $order);
        });
    }

    public function orderReady(Order $order): void
    {
        if (! $order->customer_phone || $order->channel === 'pos' && $order->type === 'dine_in') {
            return;
        }
        $orderId = $order->id;
        $this->later(function () use ($orderId) {
            $order = Order::withoutGlobalScopes()->with(['tenant', 'branch'])->find($orderId);
            if (! $order || ! ($cfg = $this->tenantEvent($order->tenant_id, 'order_ready'))) {
                return;
            }
            $msg = self::render($cfg['template'], $this->orderVars($order));
            $this->sendNow($this->tenantSender($order->tenant_id), $order->tenant_id, 'order_ready', $order->customer_phone, $msg, $order);
        });
    }

    public function closingSummary(CashClosing $closing): void
    {
        $id = $closing->id;
        $this->later(function () use ($id) {
            $c = CashClosing::withoutGlobalScopes()->with(['branch', 'user'])->find($id);
            if (! $c || ! ($cfg = $this->tenantEvent($c->tenant_id, 'closing_summary'))) {
                return;
            }
            $phones = preg_split('/[\s,;]+/', (string) $cfg['integration']->setting('owner_phones', ''), -1, PREG_SPLIT_NO_EMPTY);
            $tenant = Tenant::find($c->tenant_id);
            $msg = self::render($cfg['template'], [
                'cabang' => $c->branch?->name, 'tanggal' => $c->finalized_at?->translatedFormat('d M Y H:i'), 'kasir' => $c->user?->name,
                'jumlah_pesanan' => $c->order_count, 'tunai' => rupiah($c->cash_sales), 'non_tunai' => rupiah($c->non_cash_total),
                'selisih' => rupiah($c->difference), 'usaha' => $tenant?->name,
            ]);
            $sender = $this->tenantSender($c->tenant_id);
            foreach (array_slice($phones, 0, 5) as $phone) {
                $this->sendNow($sender, $c->tenant_id, 'closing_summary', $phone, $msg, $c);
            }
        });
    }

    protected function orderVars(Order $order): array
    {
        $pickup = $order->pickup_code ? 'Kode ambil: '.$order->pickup_code.'.' : '';

        return [
            'nama' => $order->customer_name ?: 'Pelanggan', 'nomor' => $order->number, 'total' => rupiah($order->total),
            'usaha' => optional(\App\Models\CompanyProfile::withoutGlobalScopes()->where('tenant_id', $order->tenant_id)->first())->name ?: $order->tenant?->name,
            'cabang' => $order->branch?->name, 'kode_ambil' => $pickup,
            'link_nota' => $order->tracking_token ? route('track.receipt', $order->tracking_token) : '',
            'link_lacak' => $order->tracking_token ? route('track.show', $order->tracking_token) : '',
        ];
    }

    // ---------- event platform (Super Admin → pemilik usaha) ----------

    public function invoiceCreated(SubscriptionInvoice $invoice): void
    {
        $this->platformInvoice($invoice, 'invoice_created');
    }

    public function invoicePaid(SubscriptionInvoice $invoice): void
    {
        $this->platformInvoice($invoice, 'invoice_paid');
    }

    protected function platformInvoice(SubscriptionInvoice $invoice, string $event): void
    {
        $id = $invoice->id;
        $this->later(function () use ($id, $event) {
            if (PlatformSetting::get("wa_event_{$event}", '1') !== '1') {
                return;
            }
            $inv = SubscriptionInvoice::withoutGlobalScopes()->find($id);
            $tenant = $inv ? Tenant::find($inv->tenant_id) : null;
            if (! $tenant || ! $tenant->phone) {
                return;
            }
            $template = PlatformSetting::get("wa_template_{$event}") ?: config("fnb.wa_platform_events.{$event}.template");
            $msg = self::render($template, [
                'pemilik' => $tenant->owner_name ?: 'Bapak/Ibu', 'usaha' => $tenant->name, 'nomor_tagihan' => $inv->number,
                'jumlah' => rupiah($inv->amount), 'paket' => config("fnb.plans.{$inv->plan}.label", $inv->plan),
                'durasi' => config("fnb.billing_months.{$inv->months}", $inv->months.' bulan'), 'jatuh_tempo' => $inv->due_date?->translatedFormat('d M Y'),
                'bank' => PlatformSetting::get('bank_name'), 'rekening' => PlatformSetting::get('bank_account'), 'atas_nama' => PlatformSetting::get('bank_holder'),
                'link' => route('subscription.index'), 'berlaku_sampai' => $tenant->fresh()->subscription_ends_at?->translatedFormat('d M Y'),
            ]);
            $this->sendNow($this->platformSender(), null, $event, $tenant->phone, $msg, $inv);
        });
    }
}
