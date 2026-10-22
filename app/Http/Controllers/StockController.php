<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\InventoryService;
use App\Domain\Production\CostingService;
use App\Domain\Shared\Audit;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Saldo stok (dari ledger), kartu stok, penyesuaian & saldo awal. @2026-Alifiandra */
class StockController extends Controller
{
    public function index(InventoryService $inventory, CostingService $costing)
    {
        $items = InventoryItem::where('is_active', true)->orderBy('type')->orderBy('name')->get();
        $balances = $inventory->balances($this->branchId());
        $rows = $items->map(function ($i) use ($balances, $costing) {
            $bal = $balances[$i->id] ?? 0;
            $cost = $costing->itemUnitCost($i);

            return ['item' => $i, 'balance' => $bal, 'unit_cost' => $cost, 'value' => $bal * $cost, 'low' => $i->min_stock > 0 && $bal <= $i->min_stock];
        });

        return view('inventory.stock', ['rows' => $rows, 'totalValue' => $rows->sum('value')]);
    }

    public function ledger(InventoryItem $item, InventoryService $inventory)
    {
        $movements = StockMovement::forBranch()->where('inventory_item_id', $item->id)->with('user')->latest('id')->paginate(50);

        return view('inventory.ledger', ['item' => $item, 'movements' => $movements, 'balance' => $inventory->balance($item->id, $this->branchId())]);
    }

    /** Penyesuaian manual dengan alasan wajib + audit. */
    public function adjust(Request $request, InventoryService $inventory)
    {
        $data = $request->validate([
            'inventory_item_id' => ['required', 'integer'],
            'direction' => ['required', 'in:in,out'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:200'],
        ]);
        $item = InventoryItem::findOrFail($data['inventory_item_id']);
        $qty = $data['direction'] === 'in' ? (float) $data['qty'] : -1 * (float) $data['qty'];
        $m = $inventory->post($item, $this->branchId(), 'adjustment', $qty, null, null, $data['reason']);
        Audit::log('stock.adjustment', $m, null, ['item' => $item->name, 'qty' => $qty], $data['reason']);

        return back()->with('success', 'Penyesuaian stok dicatat.');
    }

    public function openingForm(InventoryService $inventory)
    {
        $items = InventoryItem::where('is_active', true)->orderBy('name')->get();
        $hasOpening = StockMovement::forBranch()->where('type', 'opening')->pluck('inventory_item_id')->unique();

        return view('inventory.opening', compact('items', 'hasOpening'));
    }

    /** Saldo awal persediaan (wizard migrasi). Item yang sudah punya saldo awal tidak bisa ditimpa. */
    public function openingStore(Request $request, InventoryService $inventory)
    {
        $data = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*.qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);
        $done = StockMovement::forBranch()->where('type', 'opening')->pluck('inventory_item_id')->unique()->all();
        $count = 0;
        DB::transaction(function () use ($data, $done, $inventory, &$count) {
            foreach ($data['lines'] as $itemId => $line) {
                $qty = (float) ($line['qty'] ?? 0);
                if ($qty <= 0 || in_array((int) $itemId, $done, true)) {
                    continue;
                }
                $item = InventoryItem::findOrFail($itemId);
                $cost = (float) ($line['unit_cost'] ?? 0);
                $inventory->applyIncomingCost($item, $qty, $cost);
                $inventory->post($item->fresh(), $this->branchId(), 'opening', $qty, $cost, null, 'Saldo awal');
                $count++;
            }
        });
        Audit::log('stock.opening', null, null, ['items' => $count]);

        return back()->with('success', "Saldo awal {$count} bahan dicatat. Koreksi selanjutnya lewat penyesuaian/stock opname.");
    }
}
