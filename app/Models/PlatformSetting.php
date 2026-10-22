<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Pengaturan platform SaaS (kunci-nilai), dikelola Super Admin. @2026-Alifiandra */
class PlatformSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public static function get(string $key, $default = null)
    {
        $all = Cache::remember('platform_settings', 300, fn () => static::query()->pluck('value', 'key')->all());

        return $all[$key] ?? config("fnb.platform_defaults.{$key}", $default);
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget('platform_settings');
    }
}
