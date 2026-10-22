<?php

namespace App\Domain\Catalog;

use App\Domain\Inventory\InventoryService;
use App\Models\Branch;
use App\Models\BranchMenu;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Sisa porsi menu per cabang.
 *
 * Sisa porsi = jumlah porsi yang masih bisa dibuat dari stok bahan cabang
 * (menurut resep aktif), dikurangi bahan yang sudah "dipesan" oleh pesanan
 * yang belum memotong stok (belum dibayar). Bila menu punya stok porsi harian,
 * sisa porsi juga dibatasi oleh (stok harian − terjual hari ini).
 * Menu tanpa resep & tanpa stok harian = tidak dilacak (null).
 *
 * @2026-Alifiandra
 */
class MenuStockService
{
    const EPS = 0.0001;

    public function __construct(protected InventoryService $inventory) {}

    /**
     * @return array<int, array{stock: ?int, limit_by: ?string, daily_left: ?int, tracked: bool}>
     */
    public function forBranch(Branch $branch, ?Collection $menus = null): array
    {
        $menus ??= Menu::where('is_active', true)->get();
        $menus->loadMissing('recipe.items.item');
        $free = $this->freeBalances($branch);
        $daily = $this->dailyLeft($branch);

        $out = [];
        foreach ($menus as $menu) {
            $portions = null;
            $limitBy = null;
            $recipe = $menu->recipe;
            if ($recipe && $recipe->items->isNotEmpty()) {
                $yield = max((float) $recipe->yield_qty, self::EPS);
                foreach ($recipe->items as $ri) {
                    $perPortion = (float) $ri->qty / $yield;
                    if ($perPortion <= 0 || ! $ri->item) {
                        continue;
                    }
                    $can = (int) floor((max(0, $free[$ri->inventory_item_id] ?? 0) + self::EPS) / $perPortion);
                    if ($portions === null || $can < $portions) {
                        $portions = $can;
                        $limitBy = $ri->item->name;
                    }
                }
            }
            $dailyLeft = $daily[$menu->id] ?? null;
            $stock = $portions;
            if ($dailyLeft !== null && ($stock === null || $dailyLeft < $stock)) {
                $stock = $dailyLeft;
                $limitBy = 'Stok harian';
            }
            $out[$menu->id] = ['stock' => $stock, 'limit_by' => $limitBy, 'daily_left' => $dailyLeft, 'tracked' => $stock !== null];
        }

        return $out;
    }

    /**
     * Tolak keranjang bila bahan atau stok harian tidak cukup.
     * Memperhitungkan bahan yang dipakai bersama oleh beberapa menu di keranjang.
     *
     * @param  array<int, array{menu_id:int, qty:int, name:string}>  $lines
     */
    public function assertCartAvailable(Branch $branch, array $lines): void
    {
        if (! $branch->block_out_of_stock) {
            return;
        }
        $qtyByMenu = [];
        foreach ($lines as $l) {
            $qtyByMenu[$l['menu_id']] = ($qtyByMenu[$l['menu_id']] ?? 0) + (int) $l['qty'];
        }
        $menus = Menu::with('recipe.items.item')->whereIn('id', array_keys($qtyByMenu))->get()->keyBy('id');
        $info = $this->forBranch($branch, $menus);

        // 1) stok porsi harian
        foreach ($qtyByMenu as $menuId => $qty) {
            $left = $info[$menuId]['daily_left'] ?? null;
            if ($left !== null && $qty > $left) {
                throw ValidationException::withMessages(['items' => $this->message($menus[$menuId]->name, $left)]);
            }
        }

        // 2) bahan baku (gabungan seluruh keranjang)
        $need = [];
        $users = [];
        foreach ($qtyByMenu as $menuId => $qty) {
            $recipe = $menus[$menuId]->recipe ?? null;
            if (! $recipe) {
                continue;
            }
            $yield = max((float) $recipe->yield_qty, self::EPS);
            foreach ($recipe->items as $ri) {
                $need[$ri->inventory_item_id] = ($need[$ri->inventory_item_id] ?? 0) + (float) $ri->qty * $qty / $yield;
                $users[$ri->inventory_item_id][] = $menuId;
            }
        }
        if (! $need) {
            return;
        }
        $free = $this->freeBalances($branch);
        foreach ($need as $itemId => $qty) {
            if ($qty > max(0, $free[$itemId] ?? 0) + self::EPS) {
                $menuId = collect($users[$itemId])->sortBy(fn ($id) => $info[$id]['stock'] ?? PHP_INT_MAX)->first();
                throw ValidationException::withMessages(['items' => $this->message($menus[$menuId]->name, (int) ($info[$menuId]['stock'] ?? 0), count(array_unique($users[$itemId])) > 1)]);
            }
        }
    }

    /** Saldo bahan cabang dikurangi kebutuhan pesanan yang belum memotong stok. */
    public function freeBalances(Branch $branch): array
    {
        $free = $this->inventory->balances($branch->id)->all();
        $pending = Order::where('branch_id', $branch->id)->where('stock_deducted', false)
            ->where('status', '!=', 'cancelled')->with('items.menu.recipe.items')->get();
        foreach ($pending as $order) {
            foreach ($order->items as $line) {
                if ($line->kitchen_status === 'cancelled' || ! $line->menu?->recipe) {
                    continue;
                }
                $recipe = $line->menu->recipe;
                $yield = max((float) $recipe->yield_qty, self::EPS);
                foreach ($recipe->items as $ri) {
                    $free[$ri->inventory_item_id] = ($free[$ri->inventory_item_id] ?? 0) - (float) $ri->qty * $line->qty / $yield;
                }
            }
        }

        return $free;
    }

    /** @return array<int,int> menu_id => sisa stok harian */
    protected function dailyLeft(Branch $branch): array
    {
        $limits = BranchMenu::where('branch_id', $branch->id)->whereNotNull('daily_stock')->pluck('daily_stock', 'menu_id');
        if ($limits->isEmpty()) {
            return [];
        }
        $sold = OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.tenant_id', $branch->tenant_id)->where('orders.branch_id', $branch->id)
            ->where('orders.status', '!=', 'cancelled')->where('order_items.kitchen_status', '!=', 'cancelled')
            ->whereDate('orders.created_at', today())->whereIn('order_items.menu_id', $limits->keys())
            ->selectRaw('order_items.menu_id, SUM(order_items.qty) as qty')->groupBy('order_items.menu_id')->pluck('qty', 'menu_id');

        return $limits->mapWithKeys(fn ($limit, $menuId) => [$menuId => max(0, (int) $limit - (int) ($sold[$menuId] ?? 0))])->all();
    }

    protected function message(string $menu, int $left, bool $shared = false): string
    {
        if ($left <= 0) {
            return "Stok {$menu} sudah habis.";
        }

        return "Stok {$menu} tidak cukup, sisa {$left} porsi".($shared ? ' (bahan dipakai juga oleh menu lain di keranjang).' : '.');
    }
}
