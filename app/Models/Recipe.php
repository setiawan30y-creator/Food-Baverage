<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @2026-Alifiandra */
class Recipe extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'effective_from' => 'date', 'yield_qty' => 'float'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function outputItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'output_item_id');
    }
}
