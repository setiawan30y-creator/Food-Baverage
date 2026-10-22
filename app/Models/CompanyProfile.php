<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** @2026-Alifiandra */
class CompanyProfile extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    public function logoUrl(): ?string
    {
        return $this->logo_path ? route('media.logo', $this->tenant_id).'?v='.$this->updated_at?->timestamp : null;
    }
}
