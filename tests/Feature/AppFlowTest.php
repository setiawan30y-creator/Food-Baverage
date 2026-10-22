<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashClosing;
use App\Models\DiningTable;
use App\Models\Employee;
use App\Models\InventoryItem;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Recipe;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji alur utama ALMARA F&B OS. @2026-Alifiandra
 */
class AppFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function user(string $email = 'owner@contoh.test'): User
    {
        tenancy()->clear();

        return User::where('email', $email)->firstOrFail();
    }

    protected function idOf(string $class, array $where)
    {
        return $class::withoutGlobalScopes()->where($where)->firstOrFail();
    }

    public function test_login_page_and_login(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk');
        $this->post('/login', ['email' => 'owner@contoh.test', 'password' => 'password'])->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('Dasbor');
    }

    public function test_every_page_renders_for_owner(): void
    {
        $owner = $this->user();
        $menu = $this->idOf(Menu::class, ['name' => 'Corndog Mozzarella']);
        $item = $this->idOf(InventoryItem::class, ['name' => 'Kentang beku shoestring']);
        $sub = $this->idOf(Recipe::class, ['type' => 'sub_recipe']);
        $emp = $this->idOf(Employee::class, ['code' => 'K001']);
        $urls = [
            '/', '/pos', '/orders', '/tables', '/tables/print', '/kitchen', '/kitchen/feed', '/menus', '/menus/create', "/menus/{$menu->id}/edit",
            '/inventory/items', '/inventory/items/create', "/inventory/items/{$item->id}/edit", '/inventory/stock', "/inventory/stock/{$item->id}",
            '/inventory/opening', '/inventory/waste', '/inventory/counts', '/suppliers', '/purchases', '/purchases/create',
            '/recipes', '/recipes/create', '/recipes/create?type=sub_recipe', "/recipes/{$sub->id}/edit", '/production', "/production/create?recipe_id={$sub->id}",
            '/cashier', '/closings', '/employees', '/employees/create', "/employees/{$emp->id}/edit", '/schedule', '/schedule/print',
            '/attendance', '/attendance/me', '/leaves', '/payroll', '/my/payslips', '/reports/sales', '/reports/menu', '/reports/stock', '/reports/waste',
            '/reports/sales?scope=all', '/reports/sales?export=1', '/customers', '/audit', '/settings/company', '/settings/theme', '/settings/branches', '/settings/users',
            '/attendance/export', '/closings/export',
        ];
        foreach ($urls as $url) {
            $res = $this->actingAs($owner)->get($url);
            $this->assertSame(200, $res->getStatusCode(), "GET {$url} → {$res->getStatusCode()}\n".substr(strip_tags((string) $res->getContent()), 0, 600));
        }
    }

    public function test_pos_order_pay_kitchen_and_closing(): void
    {
        $cashier = $this->user('kasir@contoh.test');
        $menu = $this->idOf(Menu::class, ['name' => 'Corndog Mozzarella']);
        $mods = $menu->modifiers()->withoutGlobalScopes()->pluck('id')->take(1)->all();
        $nasi = $this->idOf(InventoryItem::class, ['name' => 'Adonan corndog']);
        $before = (float) StockMovement::withoutGlobalScopes()->where('inventory_item_id', $nasi->id)->sum('qty');

        $this->actingAs($cashier)->post('/cashier/open', ['opening_cash' => 100000])->assertRedirect();

        $payload = ['type' => 'takeaway', 'idempotency_key' => 'k-1', 'items' => [['menu_id' => $menu->id, 'qty' => 2, 'modifier_ids' => $mods]]];
        $r1 = $this->actingAs($cashier)->postJson('/pos/orders', $payload)->assertOk()->json();
        $r2 = $this->actingAs($cashier)->postJson('/pos/orders', $payload)->assertOk()->json();
        $this->assertSame($r1['id'], $r2['id'], 'Idempotency harus mencegah pesanan ganda');
        $this->assertEquals(36000, $r1['total']);

        // harga dari klien diabaikan — server menghitung sendiri
        $order = Order::withoutGlobalScopes()->find($r1['id']);
        $this->assertGreaterThan(0, (float) $order->cost_total);

        $this->actingAs($cashier)->postJson("/orders/{$order->id}/pay", ['method' => 'cash', 'received' => 30000])->assertStatus(422);
        $this->actingAs($cashier)->postJson("/orders/{$order->id}/pay", ['method' => 'qris'])->assertStatus(422); // referensi wajib
        $pay = $this->actingAs($cashier)->postJson("/orders/{$order->id}/pay", ['method' => 'cash', 'received' => 50000, 'idempotency_key' => 'p-1'])->assertOk()->json();
        $this->assertEquals(14000, $pay['change']);
        $this->actingAs($cashier)->postJson("/orders/{$order->id}/pay", ['method' => 'cash', 'received' => 50000, 'idempotency_key' => 'p-1'])->assertOk();

        $after = (float) StockMovement::withoutGlobalScopes()->where('inventory_item_id', $nasi->id)->sum('qty');
        $this->assertEqualsWithDelta($before - 180, $after, 0.01, 'Stok adonan corndog berkurang 2×90 g');

        // KDS
        $kitchen = $this->user('dapur@contoh.test');
        $feed = $this->actingAs($kitchen)->getJson('/kitchen/feed')->assertOk()->json('orders');
        $this->assertNotEmpty($feed);
        $itemId = $feed[0]['items'][0]['id'];
        $this->actingAs($kitchen)->postJson("/kitchen/items/{$itemId}", ['status' => 'preparing'])->assertOk();
        $this->actingAs($kitchen)->postJson("/kitchen/orders/{$order->id}/ready")->assertOk();
        $this->assertSame('ready', $order->fresh()->status);
        $this->actingAs($cashier)->get("/orders/{$order->id}/receipt")->assertOk()->assertSee('LUNAS');

        // Kas keluar lalu closing per pecahan dengan selisih
        $this->actingAs($cashier)->post('/cashier/movements', ['type' => 'expense', 'amount' => 5000, 'note' => 'Beli es batu'])->assertRedirect();
        $this->actingAs($cashier)->get('/cashier/close')->assertOk()->assertSee('131.000');
        $this->actingAs($cashier)->post('/cashier/close', ['count_method' => 'denomination', 'denominations' => [100000 => 1, 20000 => 1, 10000 => 1]])
            ->assertRedirect(); // tanpa alasan → ditolak (redirect back with error)
        $this->assertSame(0, CashClosing::withoutGlobalScopes()->count());
        $this->actingAs($cashier)->post('/cashier/close', ['count_method' => 'denomination', 'denominations' => [100000 => 1, 20000 => 1, 10000 => 1], 'reason' => 'Kurang kembalian'])->assertRedirect();
        $closing = CashClosing::withoutGlobalScopes()->firstOrFail();
        $this->assertEquals(131000, (float) $closing->expected_cash);
        $this->assertEquals(-1000, (float) $closing->difference);
        $this->assertSame('pending', $closing->approval_status);
        $this->actingAs($cashier)->get("/closings/{$closing->id}")->assertOk();

        $manager = $this->user('manajer@contoh.test');
        $this->actingAs($manager)->post("/closings/{$closing->id}/approve", ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('approved', $closing->fresh()->approval_status);
        $this->actingAs($manager)->post("/closings/{$closing->id}/reopen", ['reason' => 'Salah hitung pecahan'])->assertRedirect();
        $this->assertSame('reopened', $closing->fresh()->status);
        $this->actingAs($cashier)->post("/closings/{$closing->id}/reopen", ['reason' => 'coba coba'])->assertForbidden();
    }

    public function test_tenant_isolation(): void
    {
        $other = Tenant::create(['name' => 'Usaha Lain', 'slug' => 'usaha-lain']);
        tenancy()->setTenant($other);
        $b = Branch::create(['name' => 'Pusat', 'code' => 'PST']);
        $menu = Menu::create(['name' => 'Menu Rahasia', 'price' => 1000]);
        $item = InventoryItem::create(['name' => 'Bahan Rahasia', 'base_unit' => 'gram']);
        $otherUser = User::create(['tenant_id' => $other->id, 'name' => 'Pemilik Lain', 'email' => 'lain@contoh.test', 'role' => 'owner', 'password' => 'password']);
        tenancy()->clear();

        $owner = $this->user();
        $this->actingAs($owner)->get("/menus/{$menu->id}/edit")->assertNotFound();
        $this->actingAs($owner)->get("/inventory/items/{$item->id}/edit")->assertNotFound();
        $this->actingAs($owner)->put("/settings/branches/{$b->id}", ['name' => 'x'])->assertNotFound();
        $this->actingAs($owner)->put("/settings/users/{$otherUser->id}", ['name' => 'x'])->assertNotFound();
        $this->actingAs($owner)->get('/menus')->assertDontSee('Menu Rahasia');
        $this->actingAs($owner)->post('/branch/switch', ['branch_id' => $b->id])->assertNotFound();
        $this->actingAs($owner)->postJson('/pos/orders', ['type' => 'takeaway', 'idempotency_key' => 'x', 'items' => [['menu_id' => $menu->id, 'qty' => 1]]])->assertStatus(422);
    }

    public function test_role_access_is_enforced_on_backend(): void
    {
        $cashier = $this->user('kasir@contoh.test');
        $this->actingAs($cashier)->get('/settings/company')->assertForbidden();
        $this->actingAs($cashier)->get('/payroll')->assertForbidden();
        $this->actingAs($cashier)->get('/inventory/stock')->assertForbidden();
        $kitchen = $this->user('dapur@contoh.test');
        $this->actingAs($kitchen)->get('/pos')->assertForbidden();
        $this->actingAs($kitchen)->get('/kitchen')->assertOk();
    }

    public function test_qr_table_and_order_ahead_public_flow(): void
    {
        $table = $this->idOf(DiningTable::class, ['name' => 'Meja 1']);
        $menu = $this->idOf(Menu::class, ['name' => 'Es Teh Manis']);
        tenancy()->clear();
        $this->get("/t/{$table->qr_token}")->assertOk()->assertSee('Es Teh Manis');
        $this->get('/t/token-salah')->assertNotFound();
        $r = $this->postJson("/t/{$table->qr_token}", ['idempotency_key' => 'qr-1', 'customer_name' => 'Tamu', 'items' => [['menu_id' => $menu->id, 'qty' => 3]]])->assertOk()->json();
        $order = Order::withoutGlobalScopes()->where('channel', 'qr')->firstOrFail();
        $this->assertSame('new', $order->status);
        $this->assertSame('occupied', $table->fresh()->status);
        tenancy()->clear();
        $this->get(parse_url($r['track_url'], PHP_URL_PATH))->assertOk()->assertSee($order->number);

        tenancy()->clear();
        $this->get('/o/kedai-snack')->assertOk()->assertSee('Cabang Utama');
        $branch = $this->idOf(Branch::class, ['code' => 'UTM']);
        tenancy()->clear();
        $page = $this->get("/o/kedai-snack/{$branch->id}")->assertOk();
        $this->postJson("/o/kedai-snack/{$branch->id}", ['idempotency_key' => 'oa-1', 'customer_name' => 'Pemesan', 'customer_phone' => '081234567890', 'pickup' => '2000-01-01 10:00', 'items' => [['menu_id' => $menu->id, 'qty' => 1]]])
            ->assertStatus(422);
        $slot = collect(app(\App\Domain\Ordering\OrderService::class)->pickupSlots($branch))->firstWhere('full', false);
        if ($slot) {
            tenancy()->clear();
            $this->postJson("/o/kedai-snack/{$branch->id}", ['idempotency_key' => 'oa-2', 'customer_name' => 'Pemesan', 'customer_phone' => '081234567890', 'pickup' => $slot['value'], 'marketing_consent' => true, 'items' => [['menu_id' => $menu->id, 'qty' => 1]]])->assertOk();
            $oa = Order::withoutGlobalScopes()->where('channel', 'order_ahead')->firstOrFail();
            $this->assertMatchesRegularExpression('/^\d{6}$/', $oa->pickup_code);

            // verifikasi kode pickup: belum siap → ditolak
            $cashier = $this->user('kasir@contoh.test');
            $this->actingAs($cashier)->post('/orders/pickup/verify', ['code' => $oa->pickup_code])->assertRedirect();
            $this->assertNotSame('completed', $oa->fresh()->status);
        }
    }

    public function test_purchase_production_waste_and_opname(): void
    {
        $owner = $this->user();
        $cabai = $this->idOf(InventoryItem::class, ['name' => 'Keju mozzarella']);
        $before = $cabai->avg_cost;
        $this->actingAs($owner)->post('/purchases', ['date' => today()->toDateString(), 'action' => 'receive', 'lines' => [['inventory_item_id' => $cabai->id, 'qty' => 2, 'unit' => 'kilogram', 'price' => 150000]]])->assertRedirect();
        $cabai->refresh();
        $this->assertGreaterThan($before, $cabai->avg_cost, 'HPP rata-rata naik karena harga beli lebih tinggi');

        $recipe = Recipe::withoutGlobalScopes()->where('name', 'Adonan corndog')->firstOrFail();
        $cons = $recipe->items()->get()->mapWithKeys(fn ($l) => [$l->inventory_item_id => $l->qty])->all();
        $this->actingAs($owner)->post('/production', ['recipe_id' => $recipe->id, 'multiplier' => 1, 'actual_yield' => 800, 'consumptions' => $cons])->assertRedirect(); // alasan wajib
        $this->actingAs($owner)->post('/production', ['recipe_id' => $recipe->id, 'multiplier' => 1, 'actual_yield' => 800, 'consumptions' => $cons, 'variance_reason' => 'Susut'])->assertRedirect();
        $batch = \App\Models\ProductionBatch::withoutGlobalScopes()->latest('id')->first();
        $this->assertEquals(-100, (float) $batch->variance_qty);
        $this->actingAs($owner)->get("/production/{$batch->id}")->assertOk();

        // HPP menu memakai HPP sub-recipe (tanpa hitung ganda)
        $menu = $this->idOf(Menu::class, ['name' => 'Corndog Mozzarella']);
        $menuRecipe = $menu->recipe()->withoutGlobalScopes()->first();
        $costing = app(\App\Domain\Production\CostingService::class);
        tenancy()->setTenant(Tenant::first());
        $lines = $menuRecipe->items()->with('item')->get();
        $this->assertTrue($lines->contains(fn ($l) => $l->item->name === 'Adonan corndog'), 'Resep memakai sub-recipe adonan');
        $this->assertFalse($lines->contains(fn ($l) => $l->item->name === 'Tepung premix corndog'), 'Bahan mentah adonan tidak dihitung lagi');
        $adonan = InventoryItem::where('name', 'Adonan corndog')->first();
        $expected = $lines->sum(fn ($l) => $l->qty * ($l->item->id === $adonan->id ? (float) $adonan->avg_cost : (float) $l->item->avg_cost));
        $this->assertEqualsWithDelta($expected, $costing->recipeUnitCost($menuRecipe->fresh()), 0.01);
        tenancy()->clear();

        // waste besar → pending → disetujui manajer
        $owner = $this->user();
        $ayam = $this->idOf(InventoryItem::class, ['name' => 'Sosis jumbo']);
        $this->actingAs($this->user('gudang@contoh.test'))->post('/inventory/waste', ['inventory_item_id' => $ayam->id, 'qty' => 20, 'reason' => 'basi'])->assertRedirect();
        $waste = \App\Models\WasteRecord::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('pending', $waste->status);
        $this->actingAs($this->user('manajer@contoh.test'))->post("/inventory/waste/{$waste->id}/decide", ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('approved', $waste->fresh()->status);

        $this->actingAs($this->user('manajer@contoh.test'))->post('/inventory/waste', ['inventory_item_id' => $ayam->id, 'qty' => 1, 'reason' => 'tumpah'])->assertSessionHas('success');
        $this->assertSame('approved', \App\Models\WasteRecord::withoutGlobalScopes()->latest('id')->first()->status);

        // stock opname
        $this->actingAs($owner)->post('/inventory/counts')->assertRedirect();
        $count = \App\Models\StockCount::withoutGlobalScopes()->firstOrFail();
        $line = $count->lines()->where('inventory_item_id', $ayam->id)->first();
        $this->actingAs($owner)->put("/inventory/counts/{$count->id}", ['action' => 'post', 'counted' => [$line->id => 60]])->assertRedirect();
        tenancy()->clear();
        $balance = (float) StockMovement::withoutGlobalScopes()->where('inventory_item_id', $ayam->id)->sum('qty');
        $this->assertEqualsWithDelta(60, $balance, 0.001);
        $this->actingAs($owner)->get('/reports/stock')->assertOk()->assertSee('Sosis jumbo');
    }

    public function test_schedule_attendance_and_payroll(): void
    {
        $owner = $this->user();
        $cashierEmp = $this->idOf(Employee::class, ['code' => 'K002']);
        $monday = now()->startOfWeek()->toDateString();
        $this->actingAs($owner)->post('/schedule', ['cells' => [$cashierEmp->id => [$monday => 'P']]])->assertRedirect();
        $this->actingAs($owner)->get('/schedule')->assertOk();

        $cashier = $this->user('kasir@contoh.test');
        $branch = $this->idOf(Branch::class, ['code' => 'UTM']);
        $this->actingAs($cashier)->post('/attendance/punch', ['type' => 'in', 'latitude' => $branch->latitude, 'longitude' => $branch->longitude, 'accuracy' => 10])->assertRedirect();
        $this->actingAs($cashier)->post('/attendance/punch', ['type' => 'in', 'latitude' => $branch->latitude, 'longitude' => $branch->longitude])->assertRedirect();
        $this->assertSame(1, \App\Models\AttendanceEvent::withoutGlobalScopes()->count(), 'Check-in ganda ditolak');
        $ev = \App\Models\AttendanceEvent::withoutGlobalScopes()->first();
        $this->assertTrue((bool) $ev->within_geofence);
        $this->actingAs($cashier)->post('/attendance/punch', ['type' => 'out', 'latitude' => -6.3, 'longitude' => 106.9])->assertRedirect();
        $this->assertFalse((bool) \App\Models\AttendanceEvent::withoutGlobalScopes()->latest('id')->first()->within_geofence);

        $this->actingAs($owner)->post('/payroll', ['period_start' => today()->startOfMonth()->toDateString(), 'period_end' => today()->endOfMonth()->toDateString()])->assertRedirect();
        $run = \App\Models\PayrollRun::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('calculated', $run->status);
        $item = $run->items()->where('employee_id', $cashierEmp->id)->first();
        $this->assertEquals(120000, (float) $item->base_amount, '1 hari hadir × upah harian');
        $this->actingAs($owner)->get("/payroll/{$run->id}")->assertOk();
        $this->actingAs($owner)->post("/payroll/{$run->id}/approve")->assertRedirect();
        $this->actingAs($owner)->put("/payroll/items/{$item->id}", ['bonus' => 1])->assertStatus(422);
        $this->actingAs($owner)->post("/payroll/{$run->id}/pay")->assertRedirect();
        $this->assertSame('paid', $run->fresh()->status);
        $this->actingAs($cashier)->get("/payslips/{$item->id}")->assertOk()->assertSee('Slip Gaji');
        $other = $run->items()->where('employee_id', '!=', $cashierEmp->id)->first();
        $this->actingAs($cashier)->get("/payslips/{$other->id}")->assertForbidden();
    }

    public function test_settings_company_theme_branch(): void
    {
        $owner = $this->user();
        $this->actingAs($owner)->post('/settings/company', ['name' => 'Usaha Baru'])->assertRedirect();
        $this->actingAs($owner)->post('/settings/theme', ['mode' => 'dark', 'primary' => '#123456', 'accent' => '#15803D', 'background' => '#FAF7F2', 'surface' => '#FFFFFF', 'text' => '#1C1917', 'border' => '#E7E0D6', 'font' => 'Inter'])->assertRedirect();
        $this->actingAs($owner)->get('/')->assertSee('#123456', false)->assertSee('Usaha Baru');
        $this->actingAs($owner)->post('/settings/branches', ['name' => 'Cabang Dua', 'code' => 'DUA', 'geofence_radius' => 100, 'open_time' => '08:00', 'close_time' => '20:00', 'tax_percent' => 10, 'service_percent' => 5, 'pickup_slot_minutes' => 15, 'pickup_capacity_per_slot' => 5])->assertRedirect();
        $dua = $this->idOf(Branch::class, ['code' => 'DUA']);
        $this->actingAs($owner)->post('/branch/switch', ['branch_id' => $dua->id])->assertRedirect();
        $this->actingAs($owner)->get('/pos')->assertOk()->assertSee('Cabang Dua');
    }

    public function test_snack_seeder_is_idempotent_and_menu_shows_in_pos(): void
    {
        $counts = fn () => [Menu::withoutGlobalScopes()->count(), Recipe::withoutGlobalScopes()->count(), \App\Models\ProductionBatch::withoutGlobalScopes()->count(), StockMovement::withoutGlobalScopes()->count()];
        $before = $counts();
        tenancy()->clear();
        $this->seed(\Database\Seeders\SnackMenuSeeder::class);
        $this->assertSame($before, $counts());
        $this->assertSame(17, $before[0]);

        $this->actingAs($this->user('kasir@contoh.test'))->get('/pos')->assertOk()
            ->assertSee('Kentang Crispy Original')->assertSee('Kentang Spiral')->assertSee('Corndog Mozzarella')->assertSee('Paket Hemat Snack');
    }
}
