<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Isolasi data tenant: semua query otomatis difilter tenant aktif,
 * dan tenant_id diisi otomatis saat membuat data.
 * @2026-Alifiandra
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = app(Tenancy::class)->tenantId();
            if ($tenantId) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $tenantId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = app(Tenancy::class)->tenantId();
            }
            if (empty($model->tenant_id)) {
                throw new \RuntimeException('tenant_id wajib diisi untuk '.static::class);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Batasi ke cabang aktif (atau cabang tertentu). */
    public function scopeForBranch(Builder $query, ?int $branchId = null): Builder
    {
        $branchId ??= app(Tenancy::class)->branchId();

        return $query->where($this->getTable().'.branch_id', $branchId);
    }
}
