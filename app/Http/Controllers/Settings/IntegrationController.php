<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Payments\GatewayService;
use App\Domain\Shared\Audit;
use App\Domain\WhatsApp\WhatsAppSender;
use App\Domain\WhatsApp\WhatsAppService;
use App\Http\Controllers\Controller;
use App\Models\GatewayCharge;
use App\Models\PaymentAccount;
use App\Models\TenantIntegration;
use App\Models\WaMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Pengaturan → Integrasi: payment gateway (Midtrans/Xendit) & WhatsApp gateway (Fonnte/Wablas).
 * Kunci rahasia disimpan terenkripsi; kolom kosong saat menyimpan = kunci lama dipertahankan.
 * @2026-Alifiandra
 */
class IntegrationController extends Controller
{
    protected function get(string $type, ?string $provider = null): TenantIntegration
    {
        return TenantIntegration::firstOrNew(['type' => $type], ['provider' => $provider ?? ($type === 'payment' ? 'midtrans' : 'fonnte'), 'settings' => []]);
    }

    public function index()
    {
        $tenant = tenancy()->tenant();

        return view('settings.integrations', [
            'payment' => $this->get('payment'),
            'wa' => $this->get('whatsapp'),
            'hasPayment' => $tenant->hasModule('payment_gateway'),
            'hasWa' => $tenant->hasModule('whatsapp'),
            'accounts' => PaymentAccount::where('is_active', true)->orderBy('name')->get(),
            'messages' => WaMessage::where('tenant_id', $tenant->id)->latest()->limit(20)->get(),
            'charges' => GatewayCharge::with('order:id,number')->latest()->limit(15)->get(),
        ]);
    }

    public function updatePayment(Request $request)
    {
        abort_unless(tenancy()->tenant()->hasModule('payment_gateway'), 403, 'Paket Anda belum termasuk payment gateway.');
        $data = $request->validate([
            'provider' => ['required', Rule::in(array_keys(config('fnb.gateways')))],
            'channels' => ['array'], 'channels.*' => [Rule::in(array_keys(config('fnb.gateway_channels')))],
            'fee_qris_percent' => ['required', 'numeric', 'min:0', 'max:10'],
            'fee_va_flat' => ['required', 'numeric', 'min:0', 'max:50000'],
            'account_qris_id' => ['nullable', 'integer'], 'account_va_id' => ['nullable', 'integer'],
            'cred' => ['array'], 'cred.*' => ['nullable', 'string', 'max:255'],
        ]);
        $i = $this->get('payment');
        $switched = $i->exists && $i->provider !== $data['provider'];
        $i->provider = $data['provider'];
        $i->credentials = $this->mergeCreds($switched ? [] : ($i->credentials ?? []), $data['cred'] ?? [], array_keys(config("fnb.gateways.{$data['provider']}.fields")));
        $channels = array_values(array_intersect(config("fnb.gateways.{$data['provider']}.channels"), $data['channels'] ?? []));
        $i->settings = [
            'sandbox' => $request->boolean('sandbox'),
            'channels' => $channels ?: ['qris'],
            'fee_qris_percent' => (float) $data['fee_qris_percent'],
            'fee_va_flat' => (float) $data['fee_va_flat'],
            'account_qris_id' => $this->accountFor($data['account_qris_id'] ?? null, 'qris'),
            'account_va_id' => $this->accountFor($data['account_va_id'] ?? null, 'transfer'),
            'public_pay' => $request->boolean('public_pay'),
        ];
        $i->is_active = $request->boolean('is_active');
        $i->save();
        Audit::log('integration.payment_updated', $i, null, ['provider' => $i->provider, 'active' => $i->is_active, 'channels' => $channels]);

        return back()->with('success', 'Pengaturan payment gateway disimpan.'.($i->is_active ? ' Klik "Uji koneksi" untuk memastikan kunci benar.' : ''));
    }

    public function updateWhatsapp(Request $request)
    {
        abort_unless(tenancy()->tenant()->hasModule('whatsapp'), 403, 'Paket Anda belum termasuk notifikasi WhatsApp.');
        $data = $request->validate([
            'provider' => ['required', Rule::in(array_keys(config('fnb.whatsapp_providers')))],
            'cred' => ['array'], 'cred.*' => ['nullable', 'string', 'max:255'],
            'owner_phones' => ['nullable', 'string', 'max:120'],
            'events' => ['array'], 'events.*.template' => ['nullable', 'string', 'max:1000'],
        ]);
        $i = $this->get('whatsapp');
        $switched = $i->exists && $i->provider !== $data['provider'];
        $i->provider = $data['provider'];
        $i->credentials = $this->mergeCreds($switched ? [] : ($i->credentials ?? []), $data['cred'] ?? [], array_keys(config("fnb.whatsapp_providers.{$data['provider']}.fields")));
        $events = [];
        foreach (config('fnb.wa_events') as $key => $def) {
            $tpl = trim((string) ($data['events'][$key]['template'] ?? ''));
            $events[$key] = ['enabled' => $request->boolean("events.{$key}.enabled"), 'template' => $tpl !== '' && $tpl !== $def['template'] ? $tpl : null];
        }
        $i->settings = ['owner_phones' => $data['owner_phones'] ?? '', 'events' => $events];
        $i->is_active = $request->boolean('is_active');
        $i->save();
        Audit::log('integration.whatsapp_updated', $i, null, ['provider' => $i->provider, 'active' => $i->is_active]);

        return back()->with('success', 'Pengaturan WhatsApp disimpan.');
    }

    public function test(string $type)
    {
        abort_unless(in_array($type, ['payment', 'whatsapp'], true), 404);
        $i = TenantIntegration::where('type', $type)->first();
        if (! $i) {
            return back()->with('error', 'Simpan pengaturan terlebih dahulu.');
        }
        try {
            $msg = $type === 'payment' ? GatewayService::driver($i)->test() : (new WhatsAppSender(['provider' => $i->provider] + ($i->credentials ?? [])))->test();
            $i->update(['last_tested_at' => now(), 'last_error' => null]);

            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            $i->update(['last_tested_at' => now(), 'last_error' => Str::limit($e->getMessage(), 250)]);

            return back()->with('error', 'Uji koneksi gagal: '.$e->getMessage());
        }
    }

    public function sendTest(Request $request, WhatsAppService $wa)
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:30']]);
        $i = TenantIntegration::where('type', 'whatsapp')->first();
        if (! $i) {
            return back()->with('error', 'Simpan pengaturan WhatsApp terlebih dahulu.');
        }
        if (! WhatsAppSender::normalize($data['phone'])) {
            return back()->with('error', 'Nomor WhatsApp tidak valid.');
        }
        $log = $wa->sendNow(new WhatsAppSender(['provider' => $i->provider] + ($i->credentials ?? [])), tenancy()->tenantId(), 'test', $data['phone'],
            'Tes notifikasi WhatsApp dari '.(tenancy()->tenant()->name).'. Jika pesan ini masuk, integrasi sudah berfungsi.');

        return $log?->status === 'sent'
            ? back()->with('success', 'Pesan tes terkirim ke '.$log->to.'.')
            : back()->with('error', 'Pesan tes gagal: '.($log?->error ?? 'tidak diketahui'));
    }

    /** Kolom rahasia yang dikosongkan = pertahankan nilai lama. */
    protected function mergeCreds(array $old, array $new, array $allowed): array
    {
        $out = [];
        foreach ($allowed as $k) {
            $v = trim((string) ($new[$k] ?? ''));
            $out[$k] = $v !== '' ? $v : ($old[$k] ?? null);
        }

        return array_filter($out, fn ($v) => $v !== null);
    }

    protected function accountFor($id, string $method): ?int
    {
        return $id ? PaymentAccount::where('method', $method)->whereKey($id)->value('id') : null;
    }
}
