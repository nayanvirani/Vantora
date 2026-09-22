<?php

namespace App\Services;

use App\Models\FeatureConfig;
use App\Models\Shop;
use App\Services\Shopify\DiscountSyncService;
use App\Services\Shopify\ThemeSettingsSyncService;
use Illuminate\Support\Facades\Log;

/**
 * F-04 One-Click Fix, step 4 (Activate) -- the single place a feature_config
 * goes from draft to live, whether triggered from the Tools screen
 * (FeatureConfigController) or a Recipe approval (RecipeController). Both
 * must apply the same plan gate and Shopify sync, so this isn't duplicated.
 */
class FeatureActivationService
{
    /** Types that need a Shopify Function synced via DiscountSyncService on activate. */
    protected const DISCOUNT_SYNCED_TYPES = ['quantity_discount', 'bogo', 'free_gift', 'bundle'];

    public function __construct(
        protected PlanGateService $planGate,
        protected DiscountSyncService $discountSync,
        protected ThemeSettingsSyncService $themeSync,
    ) {
    }

    /**
     * @return array{ok: bool, message?: string, upgrade_required?: bool}
     */
    public function activate(Shop $shop, FeatureConfig $config): array
    {
        // Already active is NOT a no-op: the GUI settings screen calls
        // activate() every time a merchant edits and saves an already-active
        // config (there's no separate "just push the settings" action), so
        // this has to re-sync rather than short-circuit -- otherwise an
        // edited color/tier/discount never reaches Shopify, only
        // feature_configs.settings.
        if ($config->status === 'active') {
            return $this->syncToShopify($shop, $config);
        }

        if (! $this->planGate->canActivateFeature($shop, $config->type)) {
            return [
                'ok' => false,
                'upgrade_required' => true,
                'message' => "You've reached your Starter plan limit. Upgrade to Pro to unlock unlimited tools, funnels and checkout extensions.",
            ];
        }

        $syncResult = $this->syncToShopify($shop, $config);
        if (! $syncResult['ok']) {
            return $syncResult;
        }

        $config->update(['status' => 'active']);
        $this->planGate->incrementUsage($shop, $config->type);

        return ['ok' => true];
    }

    public function deactivate(Shop $shop, FeatureConfig $config): void
    {
        if ($config->status !== 'active') {
            $config->update(['status' => 'paused']);
            return;
        }

        $this->planGate->decrementUsage($shop, $config->type);

        if (in_array($config->type, self::DISCOUNT_SYNCED_TYPES, true)) {
            $this->discountSync->remove($shop, $config);
        }

        if ($this->themeSync->syncs($config->type)) {
            $this->themeSync->remove($shop, $config);
        }

        $config->update(['status' => 'paused']);
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    protected function syncToShopify(Shop $shop, FeatureConfig $config): array
    {
        if (in_array($config->type, self::DISCOUNT_SYNCED_TYPES, true)) {
            try {
                $this->discountSync->sync($shop, $config);
            } catch (\Throwable $e) {
                Log::error('Discount sync failed on activate', [
                    'shop' => $shop->domain,
                    'feature_config_id' => $config->id,
                    'type' => $config->type,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'ok' => false,
                    'message' => 'Could not sync this configuration to Shopify.',
                ];
            }
        }

        if ($this->themeSync->syncs($config->type)) {
            try {
                $this->themeSync->sync($shop, $config);
            } catch (\Throwable $e) {
                Log::error('Theme settings sync failed on activate', [
                    'shop' => $shop->domain,
                    'feature_config_id' => $config->id,
                    'type' => $config->type,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'ok' => false,
                    'message' => 'Could not sync this configuration to your storefront.',
                ];
            }
        }

        return ['ok' => true];
    }
}
