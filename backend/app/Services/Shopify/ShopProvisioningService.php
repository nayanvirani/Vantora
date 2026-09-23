<?php

namespace App\Services\Shopify;

use App\Models\Shop;

/**
 * Upserts the Shop record on install and re-syncs plan/theme data on
 * shop/update. is_plus is populated now (Phase 1) even though nothing
 * enforces it until checkout extensions exist (Phase 6-7) -- it's shop
 * data collection, not a Phase 6-7 feature, so it belongs here.
 */
class ShopProvisioningService
{
    public function install(string $domain, string $accessToken): Shop
    {
        $shop = Shop::updateOrCreate(
            ['domain' => $domain],
            [
                'access_token' => $accessToken,
                'uninstalled_at' => null,
            ]
        );

        if (! $shop->installed_at) {
            $shop->update(['installed_at' => now()]);
        }

        $this->refreshPlanEligibility($shop);

        return $shop;
    }

    public function refreshPlanEligibility(Shop $shop): void
    {
        $client = new ShopifyApiClient($shop);

        $response = $client->graphql(<<<'GQL'
            query {
                shop {
                    plan {
                        shopifyPlus
                    }
                }
                themes(first: 1, roles: [MAIN]) {
                    nodes {
                        id
                    }
                }
            }
            GQL);

        $isPlus = (bool) $response->json('data.shop.plan.shopifyPlus');
        $themeGid = $response->json('data.themes.nodes.0.id');
        $themeId = $themeGid ? (int) str($themeGid)->afterLast('/') : null;

        $shop->update([
            'is_plus' => $isPlus,
            'theme_id' => $themeId,
        ]);
    }
}
