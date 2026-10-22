<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** @2026-Alifiandra */
class Tenant extends Model
{
    protected $guarded = ['id'];

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

    public function profile(): HasOne
    {
        return $this->hasOne(CompanyProfile::class);
    }

    public function theme(): HasOne
    {
        return $this->hasOne(ThemeSetting::class);
    }

    public function isUsable(): bool
    {
        if ($this->status === 'suspended') {
            return false;
        }
        if ($this->status === 'trial' && $this->trial_ends_at && $this->trial_ends_at->isPast()) {
            return false;
        }

        return true;
    }
}
