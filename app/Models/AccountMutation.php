<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Mutasi manual rekening: setoran, penarikan, biaya admin, pindah dana, koreksi. @2026-Alifiandra */
class AccountMutation extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id'];

    const CATEGORIES = [
        'withdrawal' => 'Penarikan / setor ke pemilik',
        'fee' => 'Biaya admin / MDR',
        'expense' => 'Pembayaran biaya usaha',
        'deposit' => 'Setoran / dana masuk',
        'correction' => 'Koreksi saldo',
        'other' => 'Lainnya',
        'transfer' => 'Pindah dana antar rekening',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'date' => 'date'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }
}
