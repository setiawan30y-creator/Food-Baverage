<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** @2026-Alifiandra */
class InventoryItem extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'avg_cost' => 'float', 'min_stock' => 'float'];
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(ItemUnitConversion::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Resep sub-recipe yang menghasilkan bahan ini. */
    public function producingRecipe(): HasOne
    {
        return $this->hasOne(Recipe::class, 'output_item_id')->where('type', 'sub_recipe')->where('is_active', true)->latestOfMany('version');
    }

    public function isSubRecipe(): bool
    {
        return $this->type === 'sub_recipe';
    }

    public function factorFor(?string $unit): float
    {
        if (! $unit || $unit === $this->base_unit) {
            return 1.0;
        }
        $conv = $this->conversions->firstWhere('unit', $unit);

        return $conv ? (float) $conv->factor : 1.0;
    }
}
