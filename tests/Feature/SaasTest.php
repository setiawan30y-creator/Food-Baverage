<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Menu;
use App\Models\PlatformSetting;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AssertsTables;
use Tests\TestCase;

/**
 * SaaS: pendaftaran, paket & modul, Super Admin, tagihan, status langganan, PWA.
 * @2026-Alifiandra
 */
class SaasTest extends TestCase
{
    use AssertsTables, RefreshDatabase;

    protected bool $seed = true;

    protected function u(string $email): User
    {
        tenancy()->clear();

        return User::where('email', $email)->firstOrFail();
    }

    protected function register(array $over = [])
    {
        return $this->post('/daftar', $over + [
            'business_name' => 'Kedai Uji Coba', 'business_type' => 'snack', 'owner_name' => 'Pemilik Uji',
            'email' => 'baru@contoh.test', 'phone' => '081200000000', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
            'plan' => 'basic', 'branch_name' => 'Outlet Pusat', 'agree' => '1',
        ]);
    }

    public function test_self_registration_creates_isolated_trial_tenant(): void
    {
        $this->get('/daftar')->assertOk()->assertSee('Daftarkan usaha Anda');
        $this->register(['demo_menu' => '1'])->assertRedirect('/');
        $this->assertAuthenticated();

        $tenant = Tenant::where('slug', 'kedai-uji-coba')->firstOrFail();
        $this->assertSame('trial', $tenant->effectiveStatus());
        $this->assertSame(14, $tenant->daysLeft());
        $this->assertSame(1, $tenant->max_branches);
        $owner = User::where('email', 'baru@contoh.test')->first();
        $this->assertSame('owner', $owner->role);

        // Contoh menu snack ikut terisi, terpisah dari usaha lain
        $this->assertSame(17, Menu::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        tenancy()->clear();
        $res = $this->actingAs($owner)->get('/')->assertOk()->assertSee('Langkah awal');
        $this->actingAs($owner)->get('/customers')->assertOk()->assertDontSee('Kasir Satu');

        // Email yang sama tidak bisa dipakai lagi; slug unik
        auth()->logout();
        $this->register()->assertSessionHasErrors('email');
        $this->register(['email' => 'lain@contoh.test'])->assertRedirect('/');
        $this->assertTrue(Tenant::where('slug', 'kedai-uji-coba-2')->exists());
    }

    public function test_registration_can_be_closed(): void
    {
        PlatformSetting::put(['registration_open' => '0']);
        $this->get('/daftar')->assertNotFound();
        $this->get('/login')->assertDontSee('Daftarkan usaha');
    }

    public function test_plan_modules_hide_and_lock_features(): void
    {
        $owner = $this->u('owner@contoh.test');
        $owner->tenant->update(['plan' => 'basic']);
        $owner->refresh();

        $this->actingAs($owner)->get('/payroll')->assertForbidden();
        $this->actingAs($owner)->get('/employees')->assertForbidden();
        $this->actingAs($owner)->get('/pos')->assertOk();
        $this->actingAs($owner)->get('/')->assertOk()->assertDontSee('>Payroll<', false)->assertSee('Langganan');

        // QR meja & Order Ahead mati di paket Basic
        $table = \App\Models\DiningTable::withoutGlobalScopes()->first();
        tenancy()->clear();
        $this->get("/t/{$table->qr_token}")->assertNotFound();
        $this->get('/o/kedai-snack')->assertNotFound();
    }

    public function test_superadmin_panel_access_and_isolation(): void
    {
        $admin = $this->u('superadmin@contoh.test');
        $owner = $this->u('owner@contoh.test');

        $this->post('/login', ['email' => 'superadmin@contoh.test', 'password' => 'password'])->assertRedirect('/admin');
        $this->actingAs($admin)->get('/')->assertRedirect('/admin');
        $this->actingAs($owner)->get('/admin')->assertForbidden();
        $this->actingAs($this->u('kasir@contoh.test'))->get('/admin/tenants')->assertForbidden();

        $tenant = Tenant::where('slug', 'kedai-snack')->first();
        foreach (['/admin', '/admin/tenants', '/admin/tenants/create', "/admin/tenants/{$tenant->id}", '/admin/invoices', '/admin/settings'] as $url) {
            $res = $this->actingAs($admin)->get($url);
            $this->assertSame(200, $res->getStatusCode(), $url);
            $this->assertTables((string) $res->getContent(), $url);
        }

        // Buat penyewa manual
        $this->actingAs($admin)->post('/admin/tenants', [
            'business_name' => 'Kopi Contoh', 'business_type' => 'coffee_shop', 'owner_name' => 'Pemilik Kopi', 'email' => 'kopi@contoh.test',
            'password' => 'rahasia123', 'plan' => 'pro', 'status' => 'active',
        ])->assertRedirect();
        $kopi = Tenant::where('slug', 'kopi-contoh')->firstOrFail();
        $this->assertSame('active', $kopi->effectiveStatus());
        $this->assertSame(['Bar', 'Dapur'], \App\Models\KitchenStation::withoutGlobalScopes()->where('tenant_id', $kopi->id)->pluck('name')->all());
    }

    public function test_impersonation_is_audited_and_reversible(): void
    {
        $admin = $this->u('superadmin@contoh.test');
        $tenant = Tenant::where('slug', 'kedai-snack')->first();
        $this->actingAs($admin)->post("/admin/tenants/{$tenant->id}/impersonate")->assertRedirect('/');
        $this->assertSame('owner@contoh.test', auth()->user()->email);
        $this->get('/')->assertOk()->assertSee('Mode dukungan');
        $this->post('/impersonate/stop')->assertRedirect("/admin/tenants/{$tenant->id}");
        $this->assertSame('superadmin@contoh.test', auth()->user()->email);
        $this->assertSame(2, AuditLog::withoutGlobalScopes()->where('action', 'like', 'saas.impersonate_%')->count());

        // Pengguna biasa tidak bisa memakai "stop" untuk naik hak akses
        $this->actingAs($this->u('kasir@contoh.test'))->post('/impersonate/stop')->assertForbidden();
    }

    public function test_billing_cycle_expiry_lock_and_renewal(): void
    {
        Storage::fake('local');
        $owner = $this->u('owner@contoh.test');
        $tenant = $owner->tenant;

        // Langganan lewat > masa tenggang → hanya halaman Langganan yang terbuka
        $tenant->update(['status' => 'active', 'subscription_ends_at' => today()->subDays(10)]);
        $this->assertSame('expired', $tenant->fresh()->effectiveStatus());
        $this->actingAs($owner)->get('/pos')->assertRedirect('/langganan');
        $this->actingAs($owner)->get('/langganan')->assertOk()->assertSee('Perpanjang');
        $this->actingAs($this->u('kasir@contoh.test'))->get('/pos')->assertRedirect('/login');

        // Masa tenggang: masih bisa dipakai, ada peringatan
        $tenant->update(['subscription_ends_at' => today()->subDays(2)]);
        $this->assertSame('past_due', $tenant->fresh()->effectiveStatus());
        $this->actingAs($this->u('owner@contoh.test'))->get('/')->assertOk()->assertSee('lewat jatuh tempo');

        // Pemilik membuat tagihan 3 bulan Enterprise & mengunggah bukti
        $tenant->update(['subscription_ends_at' => today()->subDays(10)]);
        $owner = $this->u('owner@contoh.test');
        $this->actingAs($owner)->post('/langganan', ['plan' => 'enterprise', 'months' => 3])->assertRedirect();
        $inv = SubscriptionInvoice::withoutGlobalScopes()->latest('id')->first();
        $this->assertEquals(599000 * 3, (float) $inv->amount);
        $this->assertMatchesRegularExpression('/^INV-\d{6}-\d{5}$/', $inv->number);
        $this->actingAs($owner)->get("/langganan/tagihan/{$inv->id}")->assertOk()->assertSee($inv->number);
        $this->actingAs($owner)->post("/langganan/tagihan/{$inv->id}/bukti", ['proof' => UploadedFile::fake()->image('bukti.jpg'), 'payment_ref' => '1234'])->assertRedirect();
        $this->assertSame('waiting', $inv->fresh()->status);

        // Penyewa lain tidak bisa melihat tagihan ini
        $this->post('/logout');
        $this->register(['email' => 'x@contoh.test'])->assertRedirect('/');
        $this->get("/langganan/tagihan/{$inv->id}")->assertNotFound();
        auth()->logout();

        // Super Admin memverifikasi → aktif, paket & batas diterapkan
        $admin = $this->u('superadmin@contoh.test');
        $this->actingAs($admin)->get("/admin/invoices/{$inv->id}/proof")->assertOk();
        $this->actingAs($admin)->post("/admin/invoices/{$inv->id}/paid")->assertRedirect();
        $tenant->refresh();
        $this->assertSame('active', $tenant->effectiveStatus());
        $this->assertSame('enterprise', $tenant->plan);
        $this->assertSame(20, $tenant->max_branches);
        $this->assertTrue($tenant->subscription_ends_at->equalTo(today()->addMonthsNoOverflow(3)->subDay()));
        $this->actingAs($this->u('owner@contoh.test'))->get('/pos')->assertOk();
        $this->actingAs($admin)->post("/admin/invoices/{$inv->id}/paid")->assertRedirect(); // kedua kali ditolak
        $this->assertSame(1, AuditLog::withoutGlobalScopes()->where('action', 'saas.invoice_paid')->count());

        // Paket tidak boleh lebih kecil dari pemakaian
        \App\Models\Branch::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'name' => 'Cabang 2', 'code' => 'C2']);
        $this->actingAs($this->u('owner@contoh.test'))->post('/langganan', ['plan' => 'basic', 'months' => 1])->assertSessionHas('error');
    }

    public function test_suspend_and_daily_check(): void
    {
        $admin = $this->u('superadmin@contoh.test');
        $tenant = Tenant::where('slug', 'kedai-snack')->first();
        $this->actingAs($admin)->post("/admin/tenants/{$tenant->id}/suspend", ['reason' => 'Permintaan pemilik'])->assertRedirect();
        $this->actingAs($this->u('owner@contoh.test'))->get('/')->assertRedirect('/langganan');
        $this->actingAs($this->u('owner@contoh.test'))->get('/langganan')->assertOk()->assertSee('ditangguhkan');
        $this->actingAs($admin)->post("/admin/tenants/{$tenant->id}/activate")->assertRedirect();
        $this->actingAs($this->u('owner@contoh.test'))->get('/')->assertOk();

        // Pemeriksaan harian: trial yang habis → berakhir; menjelang habis → tagihan otomatis
        $this->post('/logout');
        $this->register()->assertRedirect('/');
        auth()->logout();
        $new = Tenant::where('slug', 'kedai-uji-coba')->first();
        $new->update(['trial_ends_at' => today()->addDays(2)]);
        $this->artisan('saas:check-subscriptions')->assertSuccessful();
        $this->assertSame(1, SubscriptionInvoice::withoutGlobalScopes()->where('tenant_id', $new->id)->where('status', 'unpaid')->count());
        $new->update(['trial_ends_at' => today()->subDay()]);
        $this->artisan('saas:check-subscriptions')->assertSuccessful();
        $this->assertSame('expired', $new->fresh()->status);
        $this->assertSame(1, SubscriptionInvoice::withoutGlobalScopes()->where('tenant_id', $new->id)->where('status', 'unpaid')->count(), 'tidak menggandakan tagihan');
    }

    public function test_superadmin_command(): void
    {
        $this->artisan('saas:superadmin', ['email' => 'pemilik-platform@contoh.test', '--password' => 'rahasia123'])->assertSuccessful();
        $u = User::where('email', 'pemilik-platform@contoh.test')->first();
        $this->assertTrue($u->isSuperAdmin());
        $this->artisan('saas:superadmin', ['email' => 'owner@contoh.test', '--password' => 'rahasia123'])->assertFailed();
    }

    public function test_pwa_manifest_icons_and_offline(): void
    {
        $default = $this->get('/manifest.webmanifest')->assertOk()->assertHeader('Content-Type', 'application/manifest+json')->json();
        $this->assertSame('standalone', $default['display']);
        $this->assertContains('maskable', array_column($default['icons'], 'purpose'));
        $this->assertFileExists(public_path('icons/icon-maskable-512.png'));
        $this->assertFileExists(public_path('offline.html'));
        $this->assertStringContainsString('/offline.html', file_get_contents(public_path('sw.js')));

        // Manifest staf mengikuti nama usaha & hak akses (pintasan)
        $m = $this->actingAs($this->u('kasir@contoh.test'))->get('/manifest.webmanifest')->json();
        $this->assertSame('Kedai Snack Contoh · F&B OS', $m['name']);
        $this->assertSame(['Kasir', 'Absen', 'Pesanan'], array_column($m['shortcuts'], 'name'));

        // Logo → ikon PNG persegi
        Storage::fake('local');
        $owner = $this->u('owner@contoh.test');
        $this->actingAs($owner)->post('/settings/company', ['name' => 'Kedai Snack Contoh', 'logo' => UploadedFile::fake()->image('logo.png', 300, 120)])->assertRedirect();
        $tenant = Tenant::where('slug', 'kedai-snack')->first();
        $icon = $this->get("/pwa/icon/{$tenant->id}/512.png")->assertOk()->assertHeader('Content-Type', 'image/png');
        [$w, $h] = getimagesize($icon->baseResponse->getFile()->getPathname());
        $this->assertSame([512, 512], [$w, $h]);
        $m = $this->actingAs($this->u('owner@contoh.test'))->get('/manifest.webmanifest')->json();
        $this->assertStringContainsString("/pwa/icon/{$tenant->id}/192.png", $m['icons'][0]['src']);

        // Aplikasi pelanggan per usaha
        tenancy()->clear();
        auth()->logout();
        $c = $this->get('/o/kedai-snack/manifest.webmanifest')->assertOk()->json();
        $this->assertSame('/o/kedai-snack', $c['start_url']);
        $this->assertSame('Kedai Snack Contoh', $c['name']);
        $this->get('/o/kedai-snack')->assertOk()->assertSee('/o/kedai-snack/manifest.webmanifest', false);
    }
}
