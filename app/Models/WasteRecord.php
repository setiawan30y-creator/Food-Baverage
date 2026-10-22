<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @2026-Alifiandra */
class WasteRecord extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    const REASONS = [
        'rusak' => 'Rusak', 'basi' => 'Basi', 'kedaluwarsa' => 'Kedaluwarsa', 'tumpah' => 'Tumpah',
        'gagal_produksi' => 'Gagal produksi', 'lainnya' => 'Lainnya',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'qty' => 'float'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
