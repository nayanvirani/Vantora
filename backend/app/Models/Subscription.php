<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'shop_id',
        'plan',
        'shopify_charge_id',
        'status',
        'trial_ends_at',
        'overridden_by_admin',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'overridden_by_admin' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Shop, Subscription>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
