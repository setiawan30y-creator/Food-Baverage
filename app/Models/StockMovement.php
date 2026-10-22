<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @2026-Alifiandra */
class StockMovement extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['qty' => 'float', 'unit_cost' => 'float', 'created_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
