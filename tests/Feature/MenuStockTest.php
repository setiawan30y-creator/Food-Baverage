<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sisa porsi menu dari stok bahan × resep, stok harian, dan penguncian menu habis.
 * @2026-Alifiandra
 */
class MenuStockTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function u(string $email): User
    {
        tenancy()->clear();

        return User::where('email', $email)->firstOrFail();
    }

    protected function menu(string $name): Menu
    {
        return Menu::withoutGlobalScopes()->where('name', $name)->firstOrFail();
    }

    protected function setItemStock(string $item, float $target): void
    {
        $owner = $this->u('owner@contoh.test');
        $it = InventoryItem::withoutGlobalScopes()->where('name', $item)->firstOrFail();
        $balance = (float) \App\Models\StockMovement::withoutGlobalScopes()->where('inventory_item_id', $it->id)->sum('qty');
        $diff = $target - $balance;
        if (abs($diff) > 0.0001) {
            $this->actingAs($owner)->post('/inventory/adjust', ['inventory_item_id' => $it->id, 'direction' => $diff > 0 ? 'in' : 'out', 'qty' => abs($diff), 'reason' => 'Uji stok'])->assertRedirect();
        }
    }

    protected function order(string $menu, int $qty, string $key)
    {
        return $this->actingAs($this->u('kasir@contoh.test'))->postJson('/pos/orders', [
            'type' => 'takeaway', 'idempotency_key' => $key, 'items' => [['menu_id' => $this->menu($menu)->id, 'qty' => $qty]],
        ]);
    }

    protected function stockOf(string $menu): array
    {
        $json = $this->actingAs($this->u('kasir@contoh.test'))->getJson('/pos/stock')->assertOk()->json();

        return $json[$this->menu($menu)->id];
    }

    public function test_portions_follow_ingredient_stock_and_lock_when_empty(): void
    {
        $this->assertSame(48, $this->stockOf('Air Mineral 600 ml')['stock']);
        $this->setItemStock('Air mineral 600 ml', 2);
        $this->assertSame(2, $this->stockOf('Air Mineral 600 ml')['stock']);

        $this->order('Air Mineral 600 ml', 3, 'a1')->assertStatus(422)->assertJsonFragment(['message' => 'Stok Air Mineral 600 ml tidak cukup, sisa 2 porsi.']);
        $this->order('Air Mineral 600 ml', 2, 'a2')->assertOk();

        // Pesanan belum dibayar tetap "memesan" bahan → sisa 0 & terkunci
        $s = $this->stockOf('Air Mineral 600 ml');
        $this->assertSame(0, $s['stock']);
        $this->assertTrue($s['sold_out']);
        $this->order('Air Mineral 600 ml', 1, 'a3')->assertStatus(422)->assertJsonFragment(['message' => 'Stok Air Mineral 600 ml sudah habis.']);

        // Batal → bahan kembali tersedia
        $order = \App\Models\Order::withoutGlobalScopes()->where('idempotency_key', 'a2')->first();
        $this->actingAs($this->u('kasir@contoh.test'))->post("/orders/{$order->id}/cancel", ['reason' => 'Pelanggan batal']);
        $this->assertSame(2, $this->stockOf('Air Mineral 600 ml')['stock']);

        // Tampil di halaman kasir, menu admin & dasbor
        $this->setItemStock('Air mineral 600 ml', 0);
        $air = $this->menu('Air Mineral 600 ml');
        $pos = $this->actingAs($this->u('kasir@contoh.test'))->get('/pos')->assertOk()->viewData('menus')->firstWhere('id', $air->id);
        $this->assertSame(0, $pos['stock']);
        $this->assertTrue($pos['sold_out']);
        $this->actingAs($this->u('owner@contoh.test'))->get('/menus')->assertOk()->assertSee('Sisa porsi');
        $this->actingAs($this->u('owner@contoh.test'))->get('/')->assertOk()->assertSee('Menu hampir habis')->assertSee('Air Mineral 600 ml');
    }

    public function test_shared_ingredient_across_cart_is_checked_together(): void
    {
        // Corndog Sosis & Paket Hemat sama-sama memakai 1 sosis
        $this->setItemStock('Sosis jumbo', 1);
        $this->assertSame(1, $this->stockOf('Corndog Sosis')['stock']);
        $this->assertSame(1, $this->stockOf('Paket Hemat Snack')['stock']);
        $this->assertSame(0, $this->stockOf('Corndog Mix Sosis Mozza')['stock'] <=> 2); // 0,5 sosis per porsi → 2 porsi

        $res = $this->actingAs($this->u('kasir@contoh.test'))->postJson('/pos/orders', ['type' => 'takeaway', 'idempotency_key' => 's1', 'items' => [
            ['menu_id' => $this->menu('Corndog Sosis')->id, 'qty' => 1],
            ['menu_id' => $this->menu('Paket Hemat Snack')->id, 'qty' => 1],
        ]]);
        $res->assertStatus(422);
        $this->assertStringContainsString('bahan dipakai juga oleh menu lain', $res->json('message'));
    }

    public function test_daily_stock_limit(): void
    {
        $owner = $this->u('owner@contoh.test');
        $teh = $this->menu('Es Teh Manis');
        $this->actingAs($owner)->postJson("/menus/{$teh->id}/branch", ['daily_stock' => 2])->assertOk();
        $this->assertSame(2, $this->stockOf('Es Teh Manis')['stock']);
        $this->order('Es Teh Manis', 2, 't1')->assertOk();
        $this->order('Es Teh Manis', 1, 't2')->assertStatus(422);
        $this->assertTrue($this->stockOf('Es Teh Manis')['sold_out']);
        // Hapus batas
        $this->actingAs($owner)->postJson("/menus/{$teh->id}/branch", ['daily_stock' => null])->assertOk();
        $this->order('Es Teh Manis', 1, 't3')->assertOk();
    }

    public function test_lock_can_be_turned_off_per_branch_and_public_menu_respects_it(): void
    {
        $this->setItemStock('Air mineral 600 ml', 0);
        $branch = Branch::withoutGlobalScopes()->first();

        // Pelanggan (QR) juga ditolak
        $table = \App\Models\DiningTable::withoutGlobalScopes()->first();
        tenancy()->clear();
        $this->postJson("/t/{$table->qr_token}", ['idempotency_key' => 'q1', 'items' => [['menu_id' => $this->menu('Air Mineral 600 ml')->id, 'qty' => 1]]])->assertStatus(422);
        tenancy()->clear();
        $pub = $this->get("/t/{$table->qr_token}")->assertOk()->viewData('menus')->firstWhere('id', $this->menu('Air Mineral 600 ml')->id);
        $this->assertTrue($pub['sold_out']);

        $branch->update(['block_out_of_stock' => false]);
        $this->order('Air Mineral 600 ml', 1, 'off1')->assertOk();
        $this->assertFalse($this->stockOf('Air Mineral 600 ml')['sold_out']);
    }
}
