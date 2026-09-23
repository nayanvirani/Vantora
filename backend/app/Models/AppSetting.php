<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Small, deliberately singleton app-wide config editable without a
 * deploy (maintenance mode, support email, a banner message). Anything
 * that isn't operator-facing tunable state stays in config/*.php + env
 * vars -- this is not a general key-value store.
 */
class AppSetting extends Model
{
    protected const CACHE_KEY = 'app_settings.current';

    /** @var list<string> */
    protected $fillable = [
        'maintenance_mode',
        'support_email',
        'announcement_banner',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'maintenance_mode' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => self::query()->firstOrCreate(['id' => 1]));
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
    }
}
