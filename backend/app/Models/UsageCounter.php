<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fast plan-limit checks (spec section 8): one row per shop+feature_type,
 * incremented/decremented on activate/deactivate rather than computed by
 * counting feature_configs rows every time a limit needs checking.
 */
class UsageCounter extends Model
{
    /** @use HasFactory<\Database\Factories\UsageCounterFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'shop_id',
        'feature_type',
        'active_count',
    ];

    /**
     * @return BelongsTo<Shop, UsageCounter>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
