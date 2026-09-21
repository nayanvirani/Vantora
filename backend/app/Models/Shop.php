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
        'is_plus' => 'boolean',
        'scopes' => 'array',
        'installed_at' => 'datetime',
        'uninstalled_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
    ];

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
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

    public function currentPlan(): string
    {
        return $this->subscription?->status === 'active'
            ? $this->subscription->plan
            : 'starter';
    }
}
