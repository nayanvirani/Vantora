<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Small, deliberately singleton app-wide config editable without a
 * deploy (maintenance mode, support email, a banner message). Anything
 * that isn't operator-facing tunable state stays in config/*.php + env
 * vars -- this is not a general key-value store.
 *
 * No caching here -- caught live: Cache::rememberForever() on a whole
 * Eloquent model instance unserializes as __PHP_Incomplete_Class on a
 * later request (a known fragility of caching model objects directly,
 * not just a theoretical concern). This is a single cheap row read on a
 * low-traffic admin path -- not worth the complexity of caching just
 * the plain attributes instead.
 */
class AppSetting extends Model
{
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
        return self::query()->firstOrCreate(['id' => 1]);
    }
}
