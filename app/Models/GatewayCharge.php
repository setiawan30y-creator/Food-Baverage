<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tagihan pembayaran online (QRIS dinamis / Virtual Account) ke payment gateway. @2026-Alifiandra */
class GatewayCharge extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'fee' => 'decimal:2', 'payload' => 'array', 'expires_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && (! $this->expires_at || $this->expires_at->isFuture());
    }

    public function channelLabel(): string
    {
        return config('fnb.gateway_channels.'.$this->channel, strtoupper($this->channel));
    }

    /** Data aman untuk ditampilkan di layar kasir / pelanggan. */
    public function toPublic(): array
    {
        return [
            'id' => $this->id, 'reference' => $this->reference, 'channel' => $this->channel, 'channel_label' => $this->channelLabel(),
            'amount' => (float) $this->amount, 'status' => $this->status, 'qr_string' => $this->qr_string, 'va_number' => $this->va_number,
            'expires_at' => $this->expires_at?->toIso8601String(), 'expires_label' => $this->expires_at?->format('H:i'),
        ];
    }
}
