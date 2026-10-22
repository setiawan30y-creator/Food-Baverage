<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Tenant;

/**
 * Konteks tenant & cabang aktif untuk request saat ini.
 * @2026-Alifiandra
 */
class Tenancy
{
    protected ?Tenant $tenant = null;

    protected ?Branch $branch = null;

    public function setTenant(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function setBranch(?Branch $branch): void
    {
        if ($branch && $this->tenant && $branch->tenant_id !== $this->tenant->id) {
            throw new \RuntimeException('Cabang tidak termasuk tenant aktif.');
        }
        $this->branch = $branch;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function branch(): ?Branch
    {
        return $this->branch;
    }

    public function tenantId(): ?int
    {
        return $this->tenant?->id;
    }

    public function branchId(): ?int
    {
        return $this->branch?->id;
    }

    public function clear(): void
    {
        $this->tenant = null;
        $this->branch = null;
    }
}
