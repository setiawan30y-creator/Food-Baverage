<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @2026-Alifiandra */
class Order extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    const STATUSES = ['new', 'accepted', 'preparing', 'ready', 'completed', 'cancelled'];

    protected function casts(): array
    {
        return [
            'pickup_at' => 'datetime', 'paid_at' => 'datetime', 'completed_at' => 'datetime',
            'cancelled_at' => 'datetime', 'stock_deducted' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function typeLabel(): string
    {
        return ['dine_in' => 'Makan di tempat', 'takeaway' => 'Bungkus', 'pickup' => 'Ambil (Order Ahead)'][$this->type] ?? $this->type;
    }

    public function channelLabel(): string
    {
        return ['pos' => 'Kasir', 'qr' => 'QR Meja', 'order_ahead' => 'Order Ahead'][$this->channel] ?? $this->channel;
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['completed', 'cancelled'], true);
    }
}
