<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** @2026-Alifiandra */
class ThemeSetting extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];
}
