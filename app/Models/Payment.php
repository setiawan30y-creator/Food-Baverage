<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @2026-Alifiandra */
class Payment extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    const METHODS = ['cash' => 'Tunai', 'qris' => 'QRIS', 'transfer' => 'Transfer', 'ewallet' => 'E-wallet'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
