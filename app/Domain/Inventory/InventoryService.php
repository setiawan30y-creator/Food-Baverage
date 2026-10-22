<?php

namespace App\Domain\Inventory;

use App\Domain\Shared\Audit;
use App\Models\InventoryItem;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\WasteRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ledger stok (stock_movements) adalah sumber kebenaran saldo.
 * Valuasi: weighted average per bahan (avg_cost per satuan dasar).
 * @2026-Alifiandra
 */
class InventoryService
{
    public function balance(int $itemId, int $branchId): float
    {
        return (float) StockMovement::where('inventory_item_id', $itemId)->where('branch_id', $branchId)->sum('qty');
    }

    /** @return Collection<int,float> item_id => saldo */
    public function balances(int $branchId): Collection
    {
        return StockMovement::where('branch_id', $branchId)
            ->selectRaw('inventory_item_id, SUM(qty) as balance')
            ->groupBy('inventory_item_id')
            ->pluck('balance', 'inventory_item_id')
            ->map(fn ($v) => (float) $v);
    }

    public function totalStock(int $itemId): float
    {
        return (float) StockMovement::where('inventory_item_id', $itemId)->sum('qty');
    }

    public function post(InventoryItem $item, int $branchId, string $type, float $qty, ?float $unitCost = null, $reference = null, ?string $note = null): StockMovement
    {
        $unitCost ??= (float) $item->avg_cost;

        return StockMovement::create([
            'tenant_id' => $item->tenant_id,
            'branch_id' => $branchId,
            'inventory_item_id' => $item->id,
            'type' => $type,
            'qty' => round($qty, 4),
            'unit_cost' => $unitCost,
            'total_cost' => round($qty * $unitCost, 2),
            'reference_type' => $reference ? class_basename($reference) : null,
            'reference_id' => $reference?->getKey(),
            'note' => $note,
            'user_id' => auth()->id(),
        ]);
    }

    /** Perbarui HPP rata-rata tertimbang saat barang masuk. */
    public function applyIncomingCost(InventoryItem $item, float $incomingQty, float $incomingUnitCost): void
    {
        $current = max(0, $this->totalStock($item->id));
        $newAvg = $current <= 0
            ? $incomingUnitCost
            : (($current * (float) $item->avg_cost) + ($incomingQty * $incomingUnitCost)) / ($current + $incomingQty);
        $item->update(['avg_cost' => round($newAvg, 6)]);
    }

    public function receivePurchase(Purchase $purchase): void
    {
        if ($purchase->status !== 'draft') {
            throw new \DomainException('Pembelian sudah diproses.');
        }

        DB::transaction(function () use ($purchase) {
            $purchase->load('items.item');
            foreach ($purchase->items as $line) {
                $item = $line->item;
                $unitCost = $line->base_qty > 0 ? (float) $line->subtotal / (float) $line->base_qty : 0;
                $this->applyIncomingCost($item, (float) $line->base_qty, $unitCost);
                $this->post($item->fresh(), $purchase->branch_id, 'purchase', (float) $line->base_qty, $unitCost, $purchase, 'Penerimaan '.$purchase->number);
            }
            $purchase->update(['status' => 'received', 'received_at' => now()]);
            Audit::log('purchase.received', $purchase, null, ['total' => $purchase->total]);
        });
    }

    public function approveWaste(WasteRecord $waste): void
    {
        if ($waste->status !== 'pending') {
            throw new \DomainException('Waste sudah diproses.');
        }
        DB::transaction(function () use ($waste) {
            $waste->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            $this->post($waste->item, $waste->branch_id, 'waste', -1 * (float) $waste->qty, (float) $waste->unit_cost, $waste, WasteRecord::REASONS[$waste->reason] ?? $waste->reason);
            Audit::log('waste.approved', $waste, null, ['qty' => $waste->qty, 'total' => $waste->total_cost]);
        });
    }
}
