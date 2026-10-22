<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** @2026-Alifiandra */
class Menu extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'price' => 'decimal:2'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(MenuModifier::class);
    }

    public function branchSettings(): HasMany
    {
        return $this->hasMany(BranchMenu::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class)->where('type', 'menu');
    }

    public function recipe(): HasOne
    {
        return $this->hasOne(Recipe::class)->where('type', 'menu')->where('is_active', true)->latestOfMany('version');
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? route('media.menu', $this).'?v='.$this->updated_at?->timestamp : null;
    }

    /** Apakah menu dijadwalkan tersedia pada waktu tertentu. */
    public function isScheduledAt(\DateTimeInterface $at): bool
    {
        if ($this->available_days) {
            $days = array_map('intval', explode(',', $this->available_days));
            if (! in_array((int) $at->format('N'), $days, true)) {
                return false;
            }
        }
        $t = $at->format('H:i:s');
        if ($this->available_from && $t < $this->available_from) {
            return false;
        }
        if ($this->available_until && $t > $this->available_until) {
            return false;
        }

        return true;
    }
}
