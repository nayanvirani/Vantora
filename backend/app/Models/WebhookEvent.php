<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Idempotent webhook handling (spec section 8): Shopify redelivers
 * webhooks on timeout/error, so every handler checks (topic, payload_hash)
 * here before acting, rather than assuming each delivery is unique.
 */
class WebhookEvent extends Model
{
    /** @use HasFactory<\Database\Factories\WebhookEventFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'shop_id',
        'topic',
        'payload_hash',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Shop, WebhookEvent>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
