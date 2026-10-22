<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saldo kas & rekening, rekening tujuan pembayaran, riwayat penjualan.
 * @2026-Alifiandra
 */
class FinanceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function u(string $email): User
    {
        tenancy()->clear();

        return User::where('email', $email)->firstOrFail();
    }

    protected function account(string $method): PaymentAccount
    {
        return PaymentAccount::withoutGlobalScopes()->where('method', $method)->firstOrFail();
    }

    protected function sell(string $key, array $pay)
    {
        $cashier = $this->u('kasir@contoh.test');
        $menu = Menu::withoutGlobalScopes()->where('name', 'Kentang Crispy Keju')->firstOrFail();
        $o = $this->actingAs($cashier)->postJson('/pos/orders', ['type' => 'takeaway', 'idempotency_key' => $key, 'items' => [['menu_id' => $menu->id, 'qty' => 1]]])->assertOk()->json();

        return [$o, $this->actingAs($cashier)->postJson("/orders/{$o['id']}/pay", $pay + ['idempotency_key' => 'p'.$key])];
    }

    public function test_payments_land_in_accounts_and_balances_add_up(): void
    {
        $this->actingAs($this->u('kasir@contoh.test'))->post('/cashier/open', ['opening_cash' => 100000]);
        $owner = $this->u('owner@contoh.test');
        $bank = $this->account('transfer');
        $bank->update(['opening_balance' => 1000000]);

        [$o1] = $this->sell('a', ['method' => 'cash', 'received' => 100000]);
        [$o2, $r2] = $this->sell('b', ['method' => 'qris', 'reference' => 'QR123']);
        $r2->assertOk();
        $this->assertSame($this->account('cash')->id, Payment::withoutGlobalScopes()->where('order_id', $o1['id'])->value('payment_account_id'));
        $this->assertSame($this->account('qris')->id, Payment::withoutGlobalScopes()->where('order_id', $o2['id'])->value('payment_account_id'));

        // Dua rekening transfer → kasir wajib memilih
        $owner2 = $this->actingAs($owner)->post('/keuangan/rekening', ['name' => 'Bank Kedua', 'method' => 'transfer', 'opening_balance' => 0, 'opening_date' => today()->toDateString(), 'is_active' => 1]);
        $owner2->assertRedirect();
        [, $r3] = $this->sell('c', ['method' => 'transfer', 'reference' => 'TRF1']);
        $r3->assertStatus(422)->assertJsonFragment(['message' => 'Pilih rekening tujuan pembayaran (Transfer / VA).']);
        [$o4, $r4] = $this->sell('d', ['method' => 'transfer', 'reference' => 'TRF2', 'payment_account_id' => $bank->id]);
        $r4->assertOk();
        // Rekening metode lain ditolak
        [, $r5] = $this->sell('e', ['method' => 'ewallet', 'reference' => 'EW1', 'payment_account_id' => $bank->id]);
        $r5->assertStatus(422);

        $sale = (float) Payment::withoutGlobalScopes()->where('order_id', $o4['id'])->value('amount');
        $qrisSale = (float) Payment::withoutGlobalScopes()->where('order_id', $o2['id'])->value('amount');

        // Pindah dana QRIS → bank & biaya admin bank
        $this->actingAs($owner)->post('/keuangan/pindah-dana', ['from_id' => $this->account('qris')->id, 'to_id' => $bank->id, 'amount' => $qrisSale, 'date' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post("/keuangan/rekening/{$bank->id}/mutasi", ['direction' => 'out', 'category' => 'fee', 'amount' => 2500, 'date' => today()->toDateString(), 'note' => 'Biaya admin'])->assertSessionHasNoErrors();

        $res = $this->actingAs($owner)->get('/keuangan/rekening')->assertOk()->assertSee('Saldo Kas &amp; Rekening', false);
        $rows = $res->viewData('rows')->keyBy(fn ($r) => $r['account']->id);
        $this->assertEqualsWithDelta(1000000 + $sale + $qrisSale - 2500, $rows[$bank->id]['end'], 0.01);
        $this->assertEqualsWithDelta(0, $rows[$this->account('qris')->id]['end'], 0.01);
        $this->assertSame(1, $rows[$bank->id]['sales_count']);

        // Mutasi rekening dengan saldo berjalan
        $ledger = $this->actingAs($owner)->get("/keuangan/rekening/{$bank->id}")->assertOk()->viewData('ledger');
        $this->assertCount(3, $ledger['rows']);
        $this->assertEqualsWithDelta(1000000, $ledger['start'], 0.01);
        $this->assertEqualsWithDelta($rows[$bank->id]['end'], $ledger['end'], 0.01);

        // Periode sebelum hari ini: saldo awal = saldo pembukaan saja
        $past = $this->actingAs($owner)->get('/keuangan/rekening?from='.today()->subDays(10)->toDateString().'&to='.today()->subDays(5)->toDateString())->viewData('rows')->keyBy(fn ($r) => $r['account']->id);
        $this->assertEqualsWithDelta(1000000, $past[$bank->id]['end'], 0.01);

        // Riwayat penjualan: 3 lunas (tunai, QRIS, transfer), filter metode
        $hist = $this->actingAs($this->u('kasir@contoh.test'))->get('/riwayat-penjualan')->assertOk();
        $this->assertSame(3, $hist->viewData('grand')['cnt']);
        $this->assertSame(1, $this->actingAs($this->u('kasir@contoh.test'))->get('/riwayat-penjualan?method=qris')->viewData('grand')['cnt']);
        $this->actingAs($owner)->get('/riwayat-penjualan?export=1')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($owner)->get('/keuangan/rekening?export=1')->assertOk();
    }

    public function test_access_and_plan_rules(): void
    {
        $this->actingAs($this->u('kasir@contoh.test'))->get('/keuangan/rekening')->assertForbidden();
        $this->actingAs($this->u('manajer@contoh.test'))->get('/keuangan/rekening')->assertOk();
        $this->actingAs($this->u('owner@contoh.test'))->get('/keuangan/rekening/baru')->assertOk();

        // Rekening tenant lain tidak bisa dibuka
        $other = \App\Domain\SaaS\TenantProvisioner::class;
        [$t] = app($other)->create(['business_name' => 'Usaha Lain', 'owner_name' => 'Pemilik', 'email' => 'lain@contoh.test', 'password' => 'password123', 'plan' => 'pro']);
        tenancy()->setTenant($t);
        $acc = PaymentAccount::create(['name' => 'Bank Lain', 'method' => 'transfer', 'opening_balance' => 5, 'opening_date' => today()]);
        $this->actingAs($this->u('owner@contoh.test'))->get("/keuangan/rekening/{$acc->id}")->assertNotFound();
    }

    public function test_login_page_has_password_field_enhanced_by_script(): void
    {
        $this->get('/login')->assertOk()->assertSee('js/app.js?v=', false);
        $this->assertStringContainsString('pw-toggle', file_get_contents(public_path('js/app.js')));
    }
}
