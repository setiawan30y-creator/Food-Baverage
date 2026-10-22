<?php

namespace App\Domain\Production;

use App\Domain\Inventory\InventoryService;
use App\Domain\Shared\Audit;
use App\Domain\Shared\SequenceService;
use App\Models\InventoryItem;
use App\Models\ProductionBatch;
use App\Models\Recipe;
use Illuminate\Support\Facades\DB;

/**
 * Produksi batch sub-recipe: kurangi stok bahan baku sesuai konsumsi aktual,
 * tambah stok hasil sesuai yield aktual, simpan snapshot biaya batch.
 * @2026-Alifiandra
 */
class ProductionService
{
    public function __construct(
        protected InventoryService $inventory,
        protected CostingService $costing,
        protected SequenceService $sequence,
    ) {}

    /**
     * @param  array<int,float>  $consumptions  inventory_item_id => qty aktual (satuan dasar)
     */
    public function produce(Recipe $recipe, int $branchId, float $multiplier, float $actualYield, array $consumptions, ?string $varianceReason = null): ProductionBatch
    {
        if ($recipe->type !== 'sub_recipe' || ! $recipe->output_item_id) {
            throw new \DomainException('Hanya sub-recipe yang dapat diproduksi sebagai batch.');
        }
        if ($actualYield <= 0) {
            throw new \DomainException('Yield aktual harus lebih dari 0.');
        }

        return DB::transaction(function () use ($recipe, $branchId, $multiplier, $actualYield, $consumptions, $varianceReason) {
            $planned = (float) $recipe->yield_qty * $multiplier;
            $batch = ProductionBatch::create([
                'tenant_id' => $recipe->tenant_id,
                'branch_id' => $branchId,
                'recipe_id' => $recipe->id,
                'number' => $this->sequence->next($recipe->tenant_id, $branchId, 'production', 'PRD'),
                'multiplier' => $multiplier,
                'planned_yield' => $planned,
                'actual_yield' => $actualYield,
                'variance_qty' => $actualYield - $planned,
                'variance_reason' => $varianceReason,
                'user_id' => auth()->id(),
                'produced_at' => now(),
            ]);

            $total = 0.0;
            $items = InventoryItem::whereIn('id', array_keys($consumptions))->get()->keyBy('id');
            foreach ($consumptions as $itemId => $qty) {
                $qty = (float) $qty;
                $item = $items->get($itemId);
                if (! $item || $qty <= 0) {
                    continue;
                }
                $unitCost = $this->costing->itemUnitCost($item);
                $lineTotal = $qty * $unitCost;
                $total += $lineTotal;
                $batch->consumptions()->create([
                    'inventory_item_id' => $item->id, 'qty' => $qty,
                    'unit_cost' => $unitCost, 'total_cost' => round($lineTotal, 2),
                ]);
                $this->inventory->post($item, $branchId, 'production_out', -$qty, $unitCost, $batch, 'Bahan batch '.$batch->number);
            }

            $unitCost = $total / $actualYield;
            $output = $recipe->outputItem;
            $this->inventory->applyIncomingCost($output, $actualYield, $unitCost);
            $this->inventory->post($output->fresh(), $branchId, 'production_in', $actualYield, $unitCost, $batch, 'Hasil batch '.$batch->number);

            $batch->update(['total_cost' => round($total, 2), 'unit_cost' => round($unitCost, 6)]);
            Audit::log('production.batch', $batch, null, [
                'planned' => $planned, 'actual' => $actualYield, 'total_cost' => round($total, 2), 'reason' => $varianceReason,
            ]);

            return $batch;
        });
    }
}
