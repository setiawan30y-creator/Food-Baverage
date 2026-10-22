<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Rekening / akun penerimaan: bank, QRIS, e-wallet, kas. @2026-Alifiandra */
class PaymentAccount extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:2', 'opening_date' => 'date', 'is_active' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function mutations(): HasMany
    {
        return $this->hasMany(AccountMutation::class);
    }

    /** Akun yang boleh dipakai di cabang tertentu (akun cabang itu + akun semua cabang). */
    public function scopeUsableAt(Builder $q, int $branchId): Builder
    {
        return $q->where('is_active', true)->where(fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $branchId));
    }

    public function methodLabel(): string
    {
        return config('fnb.payment_methods.'.$this->method, $this->method);
    }

    public function label(): string
    {
        return $this->name.($this->account_number ? ' · '.$this->account_number : '');
    }
}
