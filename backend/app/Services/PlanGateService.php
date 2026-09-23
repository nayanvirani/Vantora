<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\UsageCounter;

/**
 * Every create/activate action passes through here (spec section 10).
 * Limits come from config('shopify.feature_limits'): a per-(feature type,
 * plan) map, not a flat "Starter capped / Pro unlimited" split -- the
 * pricing matrix (spec section 3) caps Sticky ATC and Shipping Bar at 1
 * configuration on BOTH plans; only Trust Badges and FAQ go unlimited on
 * Pro. A type absent from the map is ungated on every plan.
 */
class PlanGateService
{
    /**
     * Whether $shop can access $module at all, independent of any
     * active-count limit -- for modules gated purely by plan (spec
     * section 10: "Pro-only modules ... hidden or locked on Starter").
     * None exist yet in Phase 1; config('shopify.pro_only_modules') is
     * empty until Phase 3+ introduces the first one.
     */
    public function canAccessModule(Shop $shop, string $module): bool
    {
        if (! in_array($module, config('shopify.pro_only_modules'), true)) {
            return true;
        }

        return $this->isAdminPaused($shop) ? false : $shop->currentPlan() === 'pro';
    }

    /**
     * Whether $shop can activate one more configuration of $featureType.
     * An admin-paused shop can never activate anything, regardless of
     * plan or limit.
     */
    public function canActivateFeature(Shop $shop, string $featureType): bool
    {
        if ($this->isAdminPaused($shop)) {
            return false;
        }

        $limit = $this->limitFor($shop, $featureType);

        if ($limit === null) {
            return true;
        }

        return $this->activeCount($shop, $featureType) < $limit;
    }

    /**
     * The configuration limit for $featureType on $shop's current plan.
     * null means unlimited; an absent config entry also means unlimited
     * (ungated type).
     */
    public function limitFor(Shop $shop, string $featureType): ?int
    {
        $limits = config('shopify.feature_limits')[$featureType] ?? null;

        if ($limits === null) {
            return null;
        }

        return $limits[$shop->currentPlan()] ?? null;
    }

    public function activeCount(Shop $shop, string $featureType): int
    {
        return UsageCounter::query()
            ->where('shop_id', $shop->id)
            ->where('feature_type', $featureType)
            ->value('active_count') ?? 0;
    }

    public function incrementUsage(Shop $shop, string $featureType): void
    {
        $counter = UsageCounter::firstOrCreate(
            ['shop_id' => $shop->id, 'feature_type' => $featureType],
            ['active_count' => 0]
        );

        $counter->increment('active_count');
    }

    public function decrementUsage(Shop $shop, string $featureType): void
    {
        $counter = UsageCounter::query()
            ->where('shop_id', $shop->id)
            ->where('feature_type', $featureType)
            ->first();

        if ($counter && $counter->active_count > 0) {
            $counter->decrement('active_count');
        }
    }

    private function isAdminPaused(Shop $shop): bool
    {
        return $shop->isAdminPaused();
    }
}
