<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Log pesan WhatsApp (tenant atau platform). Query selalu difilter tenant_id secara eksplisit. @2026-Alifiandra */
class WaMessage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['response' => 'array', 'sent_at' => 'datetime'];
    }
}
