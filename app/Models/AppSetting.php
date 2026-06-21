<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $table = 'settings';

    protected $fillable = ['key', 'value', 'type', 'description'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = Cache::remember("setting:{$key}", 300, fn() =>
            static::where('key', $key)->first()
        );

        if (!$setting) return $default;

        return match ($setting->type) {
            'integer' => (int) $setting->value,
            'float'   => (float) $setting->value,
            default   => $setting->value,
        };
    }

    public static function set(string $key, mixed $value): void
    {
        static::where('key', $key)->update(['value' => $value]);
        Cache::forget("setting:{$key}");
    }
}
