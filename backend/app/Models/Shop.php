<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain',
        'access_token',
        'refresh_token',
        'access_token_expires_at',
        'refresh_token_expires_at',
        'shopify_plan',
        'is_plus',
        'theme_id',
        'email',
        'scopes',
        'installed_at',
        'uninstalled_at',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'access_token_expires_at' => 'datetime',
        'refresh_token_expires_at' => 'datetime',
        'is_plus' => 'boolean',
        'scopes' => 'array',
        'installed_at' => 'datetime',
        'uninstalled_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * True once the access token is expired or close enough to expiring
     * (60s buffer) that it should be refreshed before use. A shop with no
     * expiry recorded predates the expiring-token migration and is treated
     * as needing a refresh on next use rather than assumed still valid.
     */
    public function needsTokenRefresh(): bool
    {
        return $this->refresh_token && (! $this->access_token_expires_at || now()->addSeconds(60)->isAfter($this->access_token_expires_at));
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    /**
     * The subscription that actually gates access -- unlike subscription()
     * (most recently created row, regardless of status), this only matches
     * a row Shopify has confirmed is 'active'. A shop mid plan-switch, or
     * whose only subscription was cancelled, correctly has none here even
     * though subscription() would still return the latest (non-active) row.
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->where('status', 'active')->latestOfMany();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasActiveSubscription(): bool
    {
        return (bool) $this->activeSubscription;
    }

    public function audits(): HasMany
    {
        return $this->hasMany(Audit::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }

    public function featureConfigs(): HasMany
    {
        return $this->hasMany(FeatureConfig::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function usageCounters(): HasMany
    {
        return $this->hasMany(UsageCounter::class);
    }

    public function extensionStatuses(): HasMany
    {
        return $this->hasMany(ExtensionStatus::class);
    }

    public function isOnTrial(): bool
    {
        return (bool) $this->subscription?->trial_ends_at?->isFuture();
    }

    /**
     * 'starter' is a conservative (least-privilege) fallback for the rare
     * code path that runs outside an HTTP request the paywall middleware
     * could gate (queued jobs, the weekly monitoring cron) -- it is NOT a
     * free tier. Every user-facing route that matters is wrapped in
     * 'active_subscription' (see routes/api.php), which blocks a shop with
     * no activeSubscription before this method is ever reached.
     */
    public function currentPlan(): string
    {
        return $this->activeSubscription?->plan ?? 'starter';
    }
}
