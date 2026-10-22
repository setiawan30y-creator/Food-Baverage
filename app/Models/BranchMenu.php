<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** @2026-Alifiandra */
class BranchMenu extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected $table = 'branch_menu';

    protected function casts(): array
    {
        return ['is_available' => 'boolean', 'sold_out' => 'boolean', 'daily_stock' => 'integer'];
    }
}
