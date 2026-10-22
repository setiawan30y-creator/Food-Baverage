<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @2026-Alifiandra */
class Employee extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    const POSITIONS = ['pemilik', 'manajer', 'kasir', 'koki', 'asisten koki', 'barista', 'pramusaji', 'gudang', 'admin', 'cleaning service', 'lainnya'];

    const WAGE_METHODS = [
        'monthly' => 'Gaji bulanan', 'daily' => 'Upah harian', 'shift' => 'Upah per shift',
        'hourly' => 'Upah per jam', 'commission' => 'Komisi', 'piece' => 'Borongan',
    ];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(EmployeeSchedule::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class);
    }

    public function advances(): HasMany
    {
        return $this->hasMany(EmployeeAdvance::class);
    }
}
