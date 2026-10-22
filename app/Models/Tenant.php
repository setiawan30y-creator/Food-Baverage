<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Penyewa (usaha). Status efektif dihitung dari tanggal sehingga tetap benar
 * walau scheduler belum berjalan: trial, active, past_due (masa tenggang),
 * expired (trial/langganan habis), suspended (dikunci Super Admin).
 * @2026-Alifiandra
 */
class Tenant extends Model
{
    protected $guarded = ['id'];

    const STATUS_LABELS = [
        'trial' => 'Trial', 'active' => 'Aktif', 'past_due' => 'Masa tenggang',
        'expired' => 'Berakhir', 'suspended' => 'Ditangguhkan',
    ];

    protected function casts(): array
    {
        return ['trial_ends_at' => 'date', 'subscription_ends_at' => 'date'];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(CompanyProfile::class);
    }

    public function theme(): HasOne
    {
        return $this->hasOne(ThemeSetting::class);
    }

    public function owner()
    {
        return $this->hasOne(User::class)->where('role', 'owner')->where('is_active', true)->oldestOfMany();
    }

    public function planConfig(): array
    {
        return config("fnb.plans.{$this->plan}") ?? config('fnb.plans.basic');
    }

    public function planLabel(): string
    {
        return $this->planConfig()['label'] ?? ucfirst((string) $this->plan);
    }

    public function hasModule(string $module): bool
    {
        return in_array($module, $this->planConfig()['modules'] ?? [], true);
    }

    /** Tanggal berakhirnya akses berbayar / trial. */
    public function accessEndsAt(): ?\Illuminate\Support\Carbon
    {
        return in_array($this->status, ['trial', 'expired'], true) && ! $this->subscription_ends_at
            ? $this->trial_ends_at
            : ($this->subscription_ends_at ?? $this->trial_ends_at);
    }

    public function effectiveStatus(): string
    {
        if ($this->status === 'suspended') {
            return 'suspended';
        }
        $today = today();
        if ($this->status === 'trial' || ($this->status === 'expired' && ! $this->subscription_ends_at)) {
            return $this->trial_ends_at && $this->trial_ends_at->lt($today) ? 'expired' : 'trial';
        }
        $end = $this->subscription_ends_at;
        if (! $end || $end->gte($today)) {
            return 'active';
        }
        $grace = (int) PlatformSetting::get('grace_days');

        return $end->copy()->addDays($grace)->gte($today) ? 'past_due' : 'expired';
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->effectiveStatus()] ?? $this->status;
    }

    public function isUsable(): bool
    {
        return in_array($this->effectiveStatus(), ['trial', 'active', 'past_due'], true);
    }

    /** Sisa hari akses (negatif = lewat). */
    public function daysLeft(): ?int
    {
        $end = $this->accessEndsAt();

        return $end ? (int) today()->diffInDays($end, false) : null;
    }

    /** Terapkan batas paket (cabang & pengguna). */
    public function applyPlanLimits(): void
    {
        $p = $this->planConfig();
        $this->max_branches = $p['max_branches'];
        $this->max_users = $p['max_users'];
    }
}
