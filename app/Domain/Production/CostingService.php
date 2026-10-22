<?php

namespace App\Domain\Production;

use App\Models\InventoryItem;
use App\Models\Menu;
use App\Models\Recipe;

/**
 * Perhitungan HPP resep & sub-recipe.
 *
 * Anti hitung-ganda: bila resep memakai sub-recipe sebagai bahan, biaya yang dipakai
 * adalah HPP per satuan sub-recipe tsb (avg_cost dari batch produksi), BUKAN
 * menjumlahkan lagi bahan mentah penyusunnya. Bila sub-recipe belum pernah diproduksi
 * (avg_cost 0), dipakai HPP teoritis dari resepnya.
 * @2026-Alifiandra
 */
class CostingService
{
    protected array $memo = [];

    public function itemUnitCost(InventoryItem $item, int $depth = 0): float
    {
        if ((float) $item->avg_cost > 0) {
            return (float) $item->avg_cost;
        }
        if ($item->isSubRecipe() && $depth < 6) {
            $recipe = $item->producingRecipe;
            if ($recipe) {
                return $this->recipeUnitCost($recipe, $depth + 1);
            }
        }

        return 0.0;
    }

    /** HPP teoritis per satuan hasil (porsi untuk menu, satuan dasar untuk sub-recipe). */
    public function recipeUnitCost(Recipe $recipe, int $depth = 0): float
    {
        $key = $recipe->id.':'.$depth;
        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }
        $recipe->loadMissing('items.item');
        $total = 0.0;
        foreach ($recipe->items as $line) {
            if ($line->item) {
                $total += (float) $line->qty * $this->itemUnitCost($line->item, $depth);
            }
        }
        $yield = max((float) $recipe->yield_qty, 0.0001);

        return $this->memo[$key] = $total / $yield;
    }

    /** Rincian biaya per baris resep. */
    public function breakdown(Recipe $recipe): array
    {
        $recipe->loadMissing('items.item');
        $rows = [];
        foreach ($recipe->items as $line) {
            $unit = $line->item ? $this->itemUnitCost($line->item) : 0;
            $rows[] = [
                'item' => $line->item,
                'qty' => (float) $line->qty,
                'unit_cost' => $unit,
                'total' => (float) $line->qty * $unit,
            ];
        }

        return $rows;
    }

    public function menuUnitCost(Menu $menu): float
    {
        $recipe = $menu->recipe;

        return $recipe ? $this->recipeUnitCost($recipe) : 0.0;
    }
}
