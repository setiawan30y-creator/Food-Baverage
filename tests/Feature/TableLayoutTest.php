<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashClosing;
use App\Models\DiningTable;
use App\Models\Employee;
use App\Models\InventoryItem;
use App\Models\Menu;
use App\Models\Order;
use App\Models\PayrollRun;
use App\Models\ProductionBatch;
use App\Models\Purchase;
use App\Models\Recipe;
use App\Models\StockCount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsTables;
use Tests\TestCase;

/**
 * Menjaga semua tabel di semua halaman: setiap baris punya jumlah sel yang sama
 * dengan judul kolom, dan perataan judul kolom sama dengan perataan isinya
 * (tidak ada data yang tampil di bawah judul kolom yang salah).
 *
 * @2026-Alifiandra
 */
class TableLayoutTest extends TestCase
{
    use AssertsTables, RefreshDatabase;

    protected bool $seed = true;

    protected function u(string $email): User
    {
        tenancy()->clear();

        return User::where('email', $email)->firstOrFail();
    }

    protected function first(string $class, array $where = [])
    {
        return $class::withoutGlobalScopes()->where($where)->orderBy('id')->firstOrFail();
    }

    /** Isi data di semua modul supaya setiap tabel punya baris untuk diperiksa. */
    protected function fillData(): void
    {
        $cashier = $this->u('kasir@contoh.test');
        $menu = $this->first(Menu::class, ['name' => 'Kentang Crispy Keju']);
        $this->actingAs($cashier)->post('/cashier/open', ['opening_cash' => 100000]);
        $o1 = $this->actingAs($cashier)->postJson('/pos/orders', ['type' => 'takeaway', 'idempotency_key' => 'a', 'discount' => 1000, 'discount_reason' => 'Promo',
            'items' => [['menu_id' => $menu->id, 'qty' => 2, 'note' => 'Pedas']]])->json();
        $this->actingAs($cashier)->postJson('/pos/orders', ['type' => 'takeaway', 'idempotency_key' => 'b', 'items' => [['menu_id' => $menu->id, 'qty' => 1]]]);
        $this->actingAs($cashier)->postJson("/orders/{$o1['id']}/pay", ['method' => 'cash', 'received' => 50000]);
        $this->actingAs($cashier)->post('/cashier/movements', ['type' => 'expense', 'amount' => 3000, 'note' => 'Beli es batu']);
        $kas = $this->first(\App\Models\PaymentAccount::class, ['method' => 'cash']);
        $bank = $this->first(\App\Models\PaymentAccount::class, ['method' => 'transfer']);
        $this->actingAs($this->u('owner@contoh.test'))->post('/keuangan/pindah-dana', ['from_id' => $kas->id, 'to_id' => $bank->id, 'amount' => 10000, 'date' => today()->toDateString()]);
        $this->actingAs($this->u('owner@contoh.test'))->post("/keuangan/rekening/{$bank->id}/mutasi", ['direction' => 'out', 'category' => 'fee', 'amount' => 2500, 'date' => today()->toDateString(), 'note' => 'Biaya admin']);

        $table = $this->first(DiningTable::class);
        tenancy()->clear();
        $this->postJson("/t/{$table->qr_token}", ['idempotency_key' => 'qr', 'items' => [['menu_id' => $menu->id, 'qty' => 1]]]);

        $owner = $this->u('owner@contoh.test');
        $keju = $this->first(InventoryItem::class, ['name' => 'Keju mozzarella']);
        $this->actingAs($owner)->post('/purchases', ['date' => today()->toDateString(), 'action' => 'receive', 'lines' => [['inventory_item_id' => $keju->id, 'qty' => 1, 'unit' => 'kilogram', 'price' => 120000]]]);
        $this->actingAs($this->u('gudang@contoh.test'))->post('/inventory/waste', ['inventory_item_id' => $keju->id, 'qty' => 900, 'reason' => 'basi', 'note' => 'Lupa masuk kulkas']);
        $this->actingAs($this->u('manajer@contoh.test'))->post('/inventory/waste', ['inventory_item_id' => $keju->id, 'qty' => 10, 'reason' => 'tumpah']);
        $this->actingAs($owner)->post('/inventory/counts');
        $this->actingAs($owner)->post('/inventory/adjust', ['inventory_item_id' => $keju->id, 'direction' => 'in', 'qty' => 5, 'reason' => 'Koreksi input']);

        $emp = $this->first(Employee::class, ['code' => 'K002']);
        $this->actingAs($owner)->post('/leaves', ['employee_id' => $emp->id, 'type' => 'lembur', 'start_date' => today()->toDateString(), 'end_date' => today()->toDateString(), 'hours' => 2, 'reason' => 'Pesanan ramai']);
        $this->actingAs($owner)->post('/attendance/manual', ['employee_id' => $emp->id, 'type' => 'in', 'occurred_at' => now()->format('Y-m-d\TH:i'), 'note' => 'GPS tidak tersedia']);
        $this->actingAs($owner)->post("/employees/{$emp->id}/advances", ['date' => today()->toDateString(), 'amount' => 200000, 'installment' => 50000]);
        $this->actingAs($owner)->post('/schedule', ['cells' => [$emp->id => [now()->startOfWeek()->toDateString() => 'P']]]);
        $this->actingAs($owner)->post('/payroll', ['period_start' => today()->startOfMonth()->toDateString(), 'period_end' => today()->endOfMonth()->toDateString()]);

        $this->actingAs($cashier)->post('/cashier/close', ['count_method' => 'denomination', 'denominations' => [100000 => 1, 20000 => 1, 10000 => 1], 'reason' => 'Uang kembalian kurang']);
        tenancy()->clear();
    }

    public function test_every_table_header_matches_its_cells(): void
    {
        $this->fillData();
        $owner = $this->u('owner@contoh.test');

        $id = fn ($class, $where = []) => $this->first($class, $where)->id;
        $from = today()->startOfMonth()->toDateString();
        $to = today()->toDateString();
        $urls = [
            '/', '/pos', '/orders', '/orders/'.$id(Order::class), '/tables', '/customers',
            '/menus', '/menus/'.$id(Menu::class).'/edit',
            '/recipes', '/recipes/create', '/recipes/create?type=sub_recipe', '/recipes/'.$id(Recipe::class, ['type' => 'menu']).'/edit',
            '/recipes/'.$id(Recipe::class, ['type' => 'sub_recipe']).'/edit',
            '/production', '/production/'.$id(ProductionBatch::class), '/production/create?recipe_id='.$id(Recipe::class, ['type' => 'sub_recipe']),
            '/inventory/stock', '/inventory/stock/'.$id(InventoryItem::class, ['name' => 'Keju mozzarella']), '/inventory/items',
            '/inventory/waste', '/inventory/counts', '/inventory/counts/'.$id(StockCount::class), '/inventory/opening',
            '/suppliers', '/purchases', '/purchases/create', '/purchases/'.$id(Purchase::class),
            '/cashier', '/closings', '/closings/'.$id(CashClosing::class),
            '/employees', '/schedule', '/attendance', '/leaves', '/payroll', '/payroll/'.$id(PayrollRun::class), '/my/payslips',
            "/reports/sales?from={$from}&to={$to}", "/reports/menu?from={$from}&to={$to}", "/reports/stock?from={$from}&to={$to}", "/reports/waste?from={$from}&to={$to}",
            '/audit', '/settings/users', '/langganan', '/settings/integrations',
            "/riwayat-penjualan?from={$from}&to={$to}", "/keuangan/rekening?from={$from}&to={$to}",
            '/keuangan/rekening/'.$id(\App\Models\PaymentAccount::class, ['method' => 'transfer'])."?from={$from}&to={$to}",
            '/keuangan/rekening/'.$id(\App\Models\PaymentAccount::class, ['method' => 'cash'])."?from={$from}&to={$to}",
        ];

        $checkedTables = 0;
        $checkedRows = 0;
        foreach ($urls as $url) {
            $res = $this->actingAs($owner)->get($url);
            $this->assertSame(200, $res->getStatusCode(), "GET {$url}");
            [$t, $r] = $this->assertTables((string) $res->getContent(), $url);
            $checkedTables += $t;
            $checkedRows += $r;
        }

        // Halaman kasir yang shift-nya masih buka (tabel kas masuk/keluar)
        $cashier = $this->u('kasir@contoh.test');
        $this->actingAs($cashier)->post('/cashier/open', ['opening_cash' => 50000]);
        $this->actingAs($cashier)->post('/cashier/movements', ['type' => 'income', 'amount' => 2000, 'note' => 'Titipan']);
        foreach (['/cashier', '/cashier/close'] as $url) {
            [$t, $r] = $this->assertTables((string) $this->actingAs($cashier)->get($url)->getContent(), $url);
            $checkedTables += $t;
            $checkedRows += $r;
        }

        $this->assertGreaterThan(40, $checkedTables, 'Jumlah tabel yang diperiksa');
        $this->assertGreaterThan(150, $checkedRows, 'Jumlah baris yang diperiksa');
    }
}
