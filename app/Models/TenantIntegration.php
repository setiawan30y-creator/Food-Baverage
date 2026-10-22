<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Pengaturan integrasi per usaha. Kredensial terenkripsi & tidak pernah ditampilkan utuh. @2026-Alifiandra */
class TenantIntegration extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id'];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array', 'settings' => 'array', 'is_active' => 'boolean', 'last_tested_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn ($m) => $m->webhook_token ??= Str::random(40));
    }

    /** Integrasi aktif tenant saat ini untuk jenis tertentu. */
    public static function active(string $type, ?int $tenantId = null): ?self
    {
        $q = $tenantId ? static::withoutGlobalScopes()->where('tenant_id', $tenantId) : static::query();

        return $q->where('type', $type)->where('is_active', true)->first();
    }

    public function cred(string $key, $default = null)
    {
        return ($this->credentials ?? [])[$key] ?? $default;
    }

    public function setting(string $key, $default = null)
    {
        return ($this->settings ?? [])[$key] ?? $default;
    }

    /** Tampilan tersamar untuk kunci rahasia, mis. "SB-Mid-…a1b2". */
    public function masked(string $key): ?string
    {
        $v = (string) $this->cred($key, '');

        return $v === '' ? null : (strlen($v) <= 8 ? str_repeat('•', strlen($v)) : substr($v, 0, 6).'…'.substr($v, -4));
    }
}
