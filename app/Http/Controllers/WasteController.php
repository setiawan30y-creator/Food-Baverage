<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\InventoryService;
use App\Domain\Production\CostingService;
use App\Domain\Shared\Audit;
use App\Models\InventoryItem;
use App\Models\WasteRecord;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Kontrol susut / waste dengan alasan wajib & persetujuan berbasis nilai. @2026-Alifiandra */
class WasteController extends Controller
{
    public function index(Request $request)
    {
        $records = WasteRecord::forBranch()->with(['item', 'requester', 'approver'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(30)->withQueryString();
        $items = InventoryItem::where('is_active', true)->orderBy('name')->get();
        $summary = WasteRecord::forBranch()->where('status', 'approved')->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->selectRaw('reason, SUM(total_cost) as total')->groupBy('reason')->pluck('total', 'reason');

        return view('inventory.waste', compact('records', 'items', 'summary'));
    }

    public function store(Request $request, CostingService $costing, InventoryService $inventory)
    {
        $data = $request->validate([
            'inventory_item_id' => ['required', 'integer'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', Rule::in(array_keys(WasteRecord::REASONS))],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $item = InventoryItem::findOrFail($data['inventory_item_id']);
        $unitCost = $costing->itemUnitCost($item);
        $waste = WasteRecord::create($data + [
            'branch_id' => $this->branchId(),
            'unit_cost' => $unitCost,
            'total_cost' => round($unitCost * (float) $data['qty'], 2),
            'requested_by' => auth()->id(),
            'status' => 'pending',
        ]);

        // Di bawah batas nilai & pelapor berwenang → langsung disetujui
        if ($waste->total_cost <= config('fnb.approval.waste_value') && $request->user()->hasAbility('inventory.approve')) {
            $inventory->approveWaste($waste);

            return back()->with('success', 'Waste dicatat & stok dikurangi.');
        }

        return back()->with('success', 'Waste dicatat dan menunggu persetujuan manajer.');
    }

    public function decide(Request $request, WasteRecord $waste, InventoryService $inventory)
    {
        $this->ensureBranch($waste);
        $data = $request->validate(['decision' => ['required', 'in:approve,reject']]);
        if ($data['decision'] === 'approve') {
            $inventory->approveWaste($waste);
        } else {
            abort_unless($waste->status === 'pending', 422);
            $waste->update(['status' => 'rejected', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            Audit::log('waste.rejected', $waste);
        }

        return back()->with('success', 'Keputusan disimpan.');
    }
}
