<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A merchant that installed the app via Shopify OAuth. This is the app's
 * only notion of "who's using it" -- there is no generic Laravel `users`
 * table, since every actor on this side is a shop, not a person.
 */
class Shop extends Model
{
    /** @use HasFactory<\Database\Factories\ShopFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'domain',
        'access_token',
        'shopify_plan',
        'is_plus',
        'theme_id',
        'installed_at',
        'uninstalled_at',
        'admin_paused_at',
    ];

    /** @var list<string> */
    protected $hidden = [
        'access_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Encrypted at rest -- the Admin API access token is as
            // sensitive as a password for this shop's data.
            'access_token' => 'encrypted',
            'is_plus' => 'boolean',
            'installed_at' => 'datetime',
            'uninstalled_at' => 'datetime',
            'admin_paused_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Subscription>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasMany<UsageCounter>
     */
    public function usageCounters(): HasMany
    {
        return $this->hasMany(UsageCounter::class);
    }

    /**
     * The shop's active plan -- 'starter' is the default for a shop with
     * no active subscription (e.g. mid-trial-setup, or Shopify has not
     * yet confirmed a charge), never treated as an error state.
     */
    public function currentPlan(): string
    {
        $active = $this->subscriptions()
            ->where('status', 'active')
            ->latest('id')
            ->first();

        return $active?->plan ?? 'starter';
    }

    public function isAdminPaused(): bool
    {
        return $this->admin_paused_at !== null;
    }
}
