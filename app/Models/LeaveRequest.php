<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @2026-Alifiandra */
class LeaveRequest extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    const TYPES = ['izin' => 'Izin', 'sakit' => 'Sakit', 'cuti' => 'Cuti', 'lembur' => 'Lembur'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'approved_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
