<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\UsageCounter;

/**
 * Single choke point for every create/activate action. Every feature
 * controller must call this before writing an active configuration.
 *
 * Spec section 10: Starter gets 1 active configuration per feature type
 * (drafts don't count); Pro-only modules are hidden/blocked on Starter.
 */
class PlanGateService
{
    public function canActivateFeature(Shop $shop, string $featureType): bool
    {
        if (in_array($featureType, config('shopify.pro_only_modules'), true)) {
            return $shop->currentPlan() === 'pro';
        }

        if ($shop->currentPlan() === 'pro') {
            return true;
        }

        if (! in_array($featureType, config('shopify.gated_feature_types'), true)) {
            return true;
        }

        $limit = config('shopify.plans.starter.per_feature_limit');
        $active = $this->activeCount($shop, $featureType);

        return $active < $limit;
    }

    public function canAccessModule(Shop $shop, string $module): bool
    {
        if (! in_array($module, config('shopify.pro_only_modules'), true)) {
            return true;
        }

        return $shop->currentPlan() === 'pro';
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
        UsageCounter::query()->updateOrCreate(
            ['shop_id' => $shop->id, 'feature_type' => $featureType],
            ['active_count' => $this->activeCount($shop, $featureType) + 1],
        );
    }

    public function decrementUsage(Shop $shop, string $featureType): void
    {
        $count = max(0, $this->activeCount($shop, $featureType) - 1);

        UsageCounter::query()->updateOrCreate(
            ['shop_id' => $shop->id, 'feature_type' => $featureType],
            ['active_count' => $count],
        );
    }

    public function aiUsageRemaining(Shop $shop): ?int
    {
        if ($shop->currentPlan() === 'starter') {
            $used = \App\Models\AiJob::query()
                ->where('shop_id', $shop->id)
                ->where('status', 'completed')
                ->count();

            return max(0, 1 - $used);
        }

        $period = now()->format('Y-m');
        $used = \App\Models\AiUsage::query()
            ->where('shop_id', $shop->id)
            ->where('period', $period)
            ->value('count') ?? 0;

        return max(0, config('shopify.ai_fair_use_cap_per_month') - $used);
    }
}
