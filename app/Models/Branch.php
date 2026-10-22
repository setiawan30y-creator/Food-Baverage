<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @2026-Alifiandra */
class Branch extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'order_ahead_enabled' => 'boolean', 'weather_widget' => 'boolean', 'is_active' => 'boolean', 'block_out_of_stock' => 'boolean',
            'tax_percent' => 'decimal:2', 'service_percent' => 'decimal:2',
        ];
    }

    public function tables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }

    public function stations(): HasMany
    {
        return $this->hasMany(KitchenStation::class);
    }

    public function isOpenAt(\DateTimeInterface $time): bool
    {
        $t = $time->format('H:i:s');

        return $t >= $this->open_time && $t <= $this->close_time;
    }
}
