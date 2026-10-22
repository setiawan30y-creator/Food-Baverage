<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\InventoryService;
use App\Domain\Production\CostingService;
use App\Domain\Shared\Audit;
use App\Domain\Shared\SequenceService;
use App\Models\InventoryItem;
use App\Models\StockCount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Stock opname: bandingkan stok teoritis vs fisik, posting selisih. @2026-Alifiandra */
class StockCountController extends Controller
{
    public function index()
    {
        $counts = StockCount::forBranch()->with('user')->latest()->paginate(20);

        return view('inventory.counts', compact('counts'));
    }

    public function store(Request $request, InventoryService $inventory, CostingService $costing, SequenceService $seq)
    {
        $balances = $inventory->balances($this->branchId());
        $count = DB::transaction(function () use ($balances, $costing, $seq, $request) {
            $count = StockCount::create([
                'branch_id' => $this->branchId(),
                'number' => $seq->next(tenancy()->tenantId(), $this->branchId(), 'opname', 'SO', 'ym', 3),
                'counted_at' => today(),
                'status' => 'draft',
                'note' => $request->input('note'),
                'user_id' => auth()->id(),
            ]);
            foreach (InventoryItem::where('is_active', true)->orderBy('name')->get() as $item) {
                $count->lines()->create([
                    'inventory_item_id' => $item->id,
                    'system_qty' => $balances[$item->id] ?? 0,
                    'unit_cost' => $costing->itemUnitCost($item),
                ]);
            }

            return $count;
        });

        return redirect()->route('inventory.counts.show', $count);
    }

    public function show(StockCount $count)
    {
        $this->ensureBranch($count);
        $count->load('lines.item');

        return view('inventory.count-show', compact('count'));
    }

    public function update(Request $request, StockCount $count, InventoryService $inventory)
    {
        $this->ensureBranch($count);
        abort_unless($count->status === 'draft', 422, 'Stock opname sudah diposting.');
        $data = $request->validate(['counted' => ['array'], 'counted.*' => ['nullable', 'numeric', 'min:0']]);
        $post = $request->input('action') === 'post';

        DB::transaction(function () use ($count, $data, $post, $inventory) {
            foreach ($count->lines()->with('item')->get() as $line) {
                $val = $data['counted'][$line->id] ?? null;
                if ($val === null || $val === '') {
                    $line->update(['counted_qty' => null, 'difference' => 0]);

                    continue;
                }
                // Saat posting, pakai saldo sistem terkini agar transaksi selama opname ikut terhitung
                $system = $post ? $inventory->balance($line->inventory_item_id, $count->branch_id) : $line->system_qty;
                $diff = (float) $val - $system;
                $line->update(['counted_qty' => $val, 'system_qty' => $system, 'difference' => $diff]);
                if ($post && abs($diff) > 0.0001) {
                    $inventory->post($line->item, $count->branch_id, 'opname', $diff, (float) $line->unit_cost, $count, 'Selisih '.$count->number);
                }
            }
            if ($post) {
                $count->update(['status' => 'posted', 'posted_at' => now()]);
                Audit::log('stock.opname_posted', $count, null, ['variance_value' => $count->lines()->get()->sum(fn ($l) => $l->difference * $l->unit_cost)]);
            }
        });

        return back()->with('success', $post ? 'Stock opname diposting, selisih masuk ledger.' : 'Hasil hitung disimpan.');
    }
}
