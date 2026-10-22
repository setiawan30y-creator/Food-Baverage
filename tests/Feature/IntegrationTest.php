<?php

namespace Tests\Feature;

use App\Models\AccountMutation;
use App\Models\GatewayCharge;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WaMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Payment gateway (Midtrans/Xendit) & WhatsApp gateway (Fonnte/Wablas) — semua panggilan API dipalsukan.
 * @2026-Alifiandra
 */
class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    const SERVER_KEY = 'SB-Mid-server-UJICOBA123456';

    protected function u(string $email): User
    {
        tenancy()->clear();

        return User::where('email', $email)->firstOrFail();
    }

    protected function newOrder(string $key, ?string $phone = null): array
    {
        $menu = Menu::withoutGlobalScopes()->where('name', 'Kentang Crispy Keju')->firstOrFail();

        return $this->actingAs($this->u('kasir@contoh.test'))->postJson('/pos/orders', array_filter([
            'type' => 'takeaway', 'idempotency_key' => $key, 'customer_name' => 'Pelanggan Uji', 'customer_phone' => $phone,
            'items' => [['menu_id' => $menu->id, 'qty' => 1]],
        ]))->assertOk()->json();
    }

    protected function setupMidtrans(): void
    {
        $this->actingAs($this->u('owner@contoh.test'))->post('/settings/integrations/payment', [
            'provider' => 'midtrans', 'cred' => ['server_key' => self::SERVER_KEY], 'channels' => ['qris', 'va_bca'],
            'sandbox' => 1, 'fee_qris_percent' => 0.7, 'fee_va_flat' => 4000, 'public_pay' => 1, 'is_active' => 1,
        ])->assertSessionHasNoErrors();
    }

    protected function midtransNotification(string $orderId, string $amount, string $status = 'settlement', ?string $key = null): array
    {
        return ['order_id' => $orderId, 'status_code' => '200', 'gross_amount' => $amount, 'transaction_status' => $status,
            'transaction_id' => 'trx-1', 'fraud_status' => 'accept',
            'signature_key' => hash('sha512', $orderId.'200'.$amount.($key ?? self::SERVER_KEY))];
    }

    public function test_midtrans_qris_flow_with_webhook_fee_and_idempotency(): void
    {
        Http::fake([
            'api.sandbox.midtrans.com/v2/charge' => Http::response(['status_code' => '201', 'transaction_id' => 'trx-1', 'qr_string' => '00020101021226QRISUJI',
                'actions' => [['name' => 'generate-qr-code', 'url' => 'https://api.sandbox.midtrans.com/v2/qris/trx-1/qr-code']], 'expiry_time' => now()->addMinutes(15)->format('Y-m-d H:i:s')]),
            'api.sandbox.midtrans.com/v2/*/status' => Http::response(['status_code' => '404', 'status_message' => 'Transaction doesn\'t exist.']),
        ]);
        $this->setupMidtrans();
        $owner = $this->u('owner@contoh.test');

        // kunci tersimpan terenkripsi & tidak tampil utuh
        $page = $this->actingAs($owner)->get('/settings/integrations')->assertOk()->assertDontSee(self::SERVER_KEY)->assertSee('SB-Mid…3456');
        $this->assertStringNotContainsString(self::SERVER_KEY, (string) \DB::table('tenant_integrations')->value('credentials'));
        $this->actingAs($owner)->post('/settings/integrations/payment/test')->assertSessionHas('success');

        $this->actingAs($this->u('kasir@contoh.test'))->post('/cashier/open', ['opening_cash' => 50000]);
        $o = $this->newOrder('g1');
        $r = $this->actingAs($this->u('kasir@contoh.test'))->postJson("/orders/{$o['id']}/gateway", ['channel' => 'qris'])->assertOk()->json('charge');
        $this->assertSame('00020101021226QRISUJI', $r['qr_string']);
        // tagihan sama dipakai ulang
        $again = $this->actingAs($this->u('kasir@contoh.test'))->postJson("/orders/{$o['id']}/gateway", ['channel' => 'qris'])->json('charge');
        $this->assertSame($r['reference'], $again['reference']);
        Http::assertSent(fn (HttpRequest $req) => str_contains($req->url(), '/v2/charge') && $req['payment_type'] === 'qris' && $req->hasHeader('Authorization'));
        // kanal yang tidak diaktifkan ditolak
        $this->actingAs($this->u('kasir@contoh.test'))->postJson("/orders/{$o['id']}/gateway", ['channel' => 'va_bri'])->assertStatus(422);

        $charge = GatewayCharge::withoutGlobalScopes()->where('reference', $r['reference'])->first();
        $amount = number_format((float) $charge->amount, 2, '.', '');
        $token = \DB::table('tenant_integrations')->value('webhook_token');
        tenancy()->clear();
        auth()->logout();

        // tanda tangan salah → ditolak, pesanan tetap belum lunas
        $this->postJson("/webhooks/payment/{$token}", $this->midtransNotification($r['reference'], $amount, 'settlement', 'kunci-palsu'))->assertStatus(401);
        $this->assertSame('unpaid', Order::withoutGlobalScopes()->find($o['id'])->payment_status);
        $this->postJson('/webhooks/payment/token-salah', $this->midtransNotification($r['reference'], $amount))->assertNotFound();

        // valid → lunas
        $this->postJson("/webhooks/payment/{$token}", $this->midtransNotification($r['reference'], $amount))->assertOk()->assertJson(['message' => 'paid']);
        $this->postJson("/webhooks/payment/{$token}", $this->midtransNotification($r['reference'], $amount))->assertJson(['message' => 'already paid']);

        $order = Order::withoutGlobalScopes()->find($o['id']);
        $this->assertSame('paid', $order->payment_status);
        $payments = Payment::withoutGlobalScopes()->where('order_id', $o['id'])->get();
        $this->assertCount(1, $payments);
        $p = $payments->first();
        $this->assertSame('qris', $p->method);
        $this->assertSame('midtrans', $p->provider);
        $this->assertEqualsWithDelta(round((float) $charge->amount * 0.007), (float) $p->provider_fee, 0.01);
        $qrisAcc = PaymentAccount::withoutGlobalScopes()->where('method', 'qris')->first();
        $this->assertSame($qrisAcc->id, $p->payment_account_id);
        $this->assertSame(1, AccountMutation::withoutGlobalScopes()->where('payment_account_id', $qrisAcc->id)->where('category', 'fee')->count());

        // kasir melihat status lunas
        $this->actingAs($this->u('kasir@contoh.test'))->getJson("/gateway-charges/{$charge->id}")->assertOk()->assertJson(['status' => 'paid', 'order_paid' => true]);
    }

    public function test_midtrans_va_and_status_polling_without_webhook(): void
    {
        Http::fake([
            'api.sandbox.midtrans.com/v2/charge' => Http::response(['status_code' => '201', 'transaction_id' => 'trx-2', 'va_numbers' => [['bank' => 'bca', 'va_number' => '12345678901']]]),
            'api.sandbox.midtrans.com/v2/*/status' => Http::response(['status_code' => '200', 'transaction_status' => 'settlement', 'fraud_status' => 'accept']),
        ]);
        $this->setupMidtrans();
        $this->actingAs($this->u('kasir@contoh.test'))->post('/cashier/open', ['opening_cash' => 50000]);
        $o = $this->newOrder('g2');
        $c = $this->actingAs($this->u('kasir@contoh.test'))->postJson("/orders/{$o['id']}/gateway", ['channel' => 'va_bca'])->assertOk()->json('charge');
        $this->assertSame('12345678901', $c['va_number']);
        Http::assertSent(fn (HttpRequest $req) => str_contains($req->url(), '/v2/charge') && ($req['bank_transfer']['bank'] ?? null) === 'bca');

        $this->actingAs($this->u('kasir@contoh.test'))->getJson("/gateway-charges/{$c['id']}")->assertJson(['status' => 'paid', 'order_paid' => true]);
        $p = Payment::withoutGlobalScopes()->where('order_id', $o['id'])->first();
        $this->assertSame('transfer', $p->method);
        $this->assertEquals(4000, (float) $p->provider_fee);
    }

    public function test_xendit_qris_webhook_token_and_customer_self_pay(): void
    {
        Http::fake([
            'api.xendit.co/qr_codes' => Http::response(['id' => 'qr_123', 'qr_string' => 'XENDITQR', 'status' => 'ACTIVE', 'expires_at' => now()->addMinutes(15)->toIso8601String()]),
            'api.xendit.co/balance' => Http::response(['balance' => 150000]),
        ]);
        $owner = $this->u('owner@contoh.test');
        $this->actingAs($owner)->post('/settings/integrations/payment', [
            'provider' => 'xendit', 'cred' => ['secret_key' => 'xnd_development_RAHASIA', 'callback_token' => 'cbtoken123'],
            'channels' => ['qris'], 'fee_qris_percent' => 0.7, 'fee_va_flat' => 4500, 'public_pay' => 1, 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post('/settings/integrations/payment/test')->assertSessionHas('success', 'Terhubung ke Xendit (mode Test). Saldo: Rp 150.000.');

        // Pelanggan bayar sendiri dari halaman lacak (pesanan QR meja)
        $this->newOrder('x1', '081234567890');
        $order = Order::withoutGlobalScopes()->where('idempotency_key', 'x1')->first();
        tenancy()->clear();
        auth()->logout();
        $this->get("/track/{$order->tracking_token}")->assertOk()->assertSee('Bayar sekarang');
        $c = $this->postJson("/track/{$order->tracking_token}/pay", ['channel' => 'qris'])->assertOk()->json('charge');
        $this->assertSame('XENDITQR', $c['qr_string']);
        Http::assertSent(fn (HttpRequest $req) => str_contains($req->url(), 'qr_codes') && $req->header('api-version')[0] === '2022-07-31' && $req['type'] === 'DYNAMIC');

        $token = \DB::table('tenant_integrations')->value('webhook_token');
        $body = ['event' => 'qr.payment', 'data' => ['reference_id' => $c['reference'], 'qr_id' => 'qr_123', 'amount' => $c['amount'], 'status' => 'SUCCEEDED']];
        $this->postJson("/webhooks/payment/{$token}", $body, ['X-CALLBACK-TOKEN' => 'salah'])->assertStatus(401);
        $this->postJson("/webhooks/payment/{$token}", $body, ['X-CALLBACK-TOKEN' => 'cbtoken123'])->assertOk()->assertJson(['message' => 'paid']);
        $this->getJson("/track/{$order->tracking_token}/pay/{$c['reference']}")->assertJson(['order_paid' => true]);
        $this->get("/track/{$order->tracking_token}")->assertDontSee('Bayar sekarang');

        // nominal kurang ditolak
        $o2 = $this->newOrder('x2');
        $o2m = Order::withoutGlobalScopes()->find($o2['id']);
        tenancy()->clear();
        $c2 = $this->postJson("/track/{$o2m->tracking_token}/pay", ['channel' => 'qris'])->json('charge');
        $this->postJson("/webhooks/payment/{$token}", ['event' => 'qr.payment', 'data' => ['reference_id' => $c2['reference'], 'amount' => 1000, 'status' => 'SUCCEEDED']],
            ['X-CALLBACK-TOKEN' => 'cbtoken123'])->assertJson(['message' => 'amount mismatch']);
        $this->assertSame('unpaid', $o2m->fresh()->payment_status);
    }

    public function test_whatsapp_notifications_for_orders_closing_and_subscription(): void
    {
        $fail = false;
        Http::fake([
            'api.fonnte.com/send' => function () use (&$fail) {
                return $fail ? Http::response(['status' => false, 'reason' => 'token invalid']) : Http::response(['status' => true, 'detail' => 'success! message in queue']);
            },
            'api.fonnte.com/device' => Http::response(['status' => true, 'device' => '6280000000000', 'device_status' => 'connect']),
        ]);
        $owner = $this->u('owner@contoh.test');
        $this->actingAs($owner)->post('/settings/integrations/whatsapp', [
            'provider' => 'fonnte', 'cred' => ['token' => 'TOKENFONNTE'], 'owner_phones' => '081111111111',
            'events' => ['order_paid' => ['enabled' => 1], 'order_ready' => ['enabled' => 1], 'closing_summary' => ['enabled' => 1]], 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post('/settings/integrations/whatsapp/test')->assertSessionHas('success');
        $this->actingAs($owner)->post('/settings/integrations/whatsapp/send-test', ['phone' => '0812-0000-1111'])->assertSessionHas('success', 'Pesan tes terkirim ke 6281200001111.');

        $cashier = $this->u('kasir@contoh.test');
        $this->actingAs($cashier)->post('/cashier/open', ['opening_cash' => 50000]);
        $o = $this->newOrder('w1', '0812 3456 7890');
        $this->actingAs($cashier)->postJson("/orders/{$o['id']}/pay", ['method' => 'cash', 'received' => 100000, 'idempotency_key' => 'pw1'])->assertOk();
        $this->actingAs($cashier)->post("/orders/{$o['id']}/status", ['status' => 'ready'])->assertRedirect();
        $this->actingAs($cashier)->post('/cashier/close', ['count_method' => 'total', 'counted_total' => 999999, 'reason' => 'Uji']);

        $sent = WaMessage::orderBy('id')->get();
        $paid = $sent->firstWhere('event', 'order_paid');
        $this->assertNotNull($paid);
        $this->assertSame('6281234567890', $paid->to);
        $this->assertSame('sent', $paid->status);
        $this->assertStringContainsString('Terima kasih Pelanggan Uji!', $paid->message);
        $this->assertStringContainsString('/nota/', $paid->message);
        $this->assertNotNull($sent->firstWhere('event', 'order_ready'));
        $this->assertSame('6281111111111', optional($sent->firstWhere('event', 'closing_summary'))->to);
        Http::assertSent(fn (HttpRequest $req) => str_contains($req->url(), 'fonnte.com/send') && $req['target'] === '6281234567890' && $req->header('Authorization')[0] === 'TOKENFONNTE');

        // Gagal kirim tercatat, tidak menggagalkan transaksi
        $fail = true;
        $o2 = $this->newOrder('w2', '081299998888');
        $this->actingAs($cashier)->post('/cashier/open', ['opening_cash' => 0]);
        $this->actingAs($cashier)->postJson("/orders/{$o2['id']}/pay", ['method' => 'cash', 'received' => 100000, 'idempotency_key' => 'pw2'])->assertOk();
        $this->assertSame('failed', WaMessage::where('to', '6281299998888')->value('status'));

        // Platform: tagihan langganan → WhatsApp ke nomor pemilik usaha
        $fail = false;
        $this->actingAs($this->u('superadmin@contoh.test'))->post('/admin/settings/whatsapp', [
            'wa_enabled' => 1, 'wa_provider' => 'fonnte', 'wa_token' => 'TOKENPLATFORM', 'wa_event_invoice_created' => 1, 'wa_event_invoice_paid' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertStringNotContainsString('TOKENPLATFORM', (string) \DB::table('platform_settings')->where('key', 'wa_token')->value('value'));
        $this->actingAs($owner)->post('/langganan', ['plan' => 'pro', 'months' => 1])->assertRedirect();
        $inv = WaMessage::whereNull('tenant_id')->where('event', 'invoice_created')->first();
        $this->assertNotNull($inv);
        $this->assertSame('6280000000000', $inv->to);
        $this->assertStringContainsString('Kedai Snack Contoh', $inv->message);
    }

    public function test_plan_rules_and_pages(): void
    {
        $owner = $this->u('owner@contoh.test');
        $this->actingAs($owner)->get('/settings/integrations')->assertOk()->assertSee('Payment gateway')->assertSee('WhatsApp');
        $this->actingAs($this->u('kasir@contoh.test'))->get('/settings/integrations')->assertForbidden();
        $this->actingAs($this->u('superadmin@contoh.test'))->get('/admin/settings')->assertOk()->assertSee('WhatsApp platform');

        Tenant::where('slug', 'kedai-snack')->update(['plan' => 'basic']);
        $this->actingAs($this->u('owner@contoh.test'))->get('/settings/integrations')->assertOk()->assertSee('Notifikasi WhatsApp tersedia di paket Pro');
        $this->actingAs($this->u('owner@contoh.test'))->post('/settings/integrations/whatsapp', ['provider' => 'fonnte', 'is_active' => 1])->assertForbidden();
        // tanpa gateway aktif, kasir tidak bisa membuat tagihan online
        $this->actingAs($this->u('kasir@contoh.test'))->post('/cashier/open', ['opening_cash' => 0]);
        $o = $this->newOrder('p1');
        $this->actingAs($this->u('kasir@contoh.test'))->postJson("/orders/{$o['id']}/gateway", ['channel' => 'qris'])->assertStatus(422)
            ->assertJsonFragment(['message' => 'Payment gateway belum diaktifkan. Atur di Pengaturan → Integrasi.']);
    }
}
