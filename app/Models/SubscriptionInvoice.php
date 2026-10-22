<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tagihan langganan SaaS. @2026-Alifiandra */
class SubscriptionInvoice extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    const STATUS_LABELS = ['unpaid' => 'Belum bayar', 'waiting' => 'Menunggu verifikasi', 'paid' => 'Lunas', 'void' => 'Dibatalkan'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date', 'period_start' => 'date', 'period_end' => 'date',
            'paid_at' => 'datetime', 'proof_uploaded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $inv) {
            if (! $inv->number) {
                $inv->updateQuietly(['number' => 'INV-'.now()->format('Ym').'-'.str_pad((string) $inv->id, 5, '0', STR_PAD_LEFT)]);
            }
        });
    }

    public function tenantRecord(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['unpaid', 'waiting'], true);
    }

    public static function priceFor(string $plan, int $months): float
    {
        $monthly = (float) (config("fnb.plans.{$plan}.price") ?? 0);
        $billable = $months >= 12 ? $months - 2 * intdiv($months, 12) : $months;

        return $monthly * $billable;
    }
}
