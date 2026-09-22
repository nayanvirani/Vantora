<?php

namespace App\Services\Shopify;

use App\Models\FeatureConfig;
use App\Models\Shop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Turns an activated feature_config into a real Shopify discount (or, for
 * bundles, a product metafield the Cart Transform function reads). Function
 * IDs aren't hardcoded -- they're resolved from the Admin API at sync time
 * against the titles in each extension's locales/en.default.json, since the
 * IDs Shopify assigns are only known after `shopify app deploy` and can
 * change across app versions.
 *
 * NOT YET VERIFIED against a live store: exercising this needs a deployed
 * app (`shopify app deploy`, which needs an interactive Partner Dashboard
 * login this environment can't do) and a dev store with app + function
 * installed. The five Shopify Functions themselves are covered by
 * `npx vitest run` in each extensions/<name> and were verified that way;
 * this service has not had the same level of scrutiny. Sanity-check the
 * mutation shapes against shopify.dev before relying on this in production.
 */
class DiscountSyncService
{
    protected const FUNCTION_TITLES = [
        'quantity_discount' => 'Vantora Quantity Discount',
        'bogo' => 'Vantora BOGO Offer',
        'free_gift' => 'Vantora Free Gift',
        'bundle' => 'Vantora Bundle Discount',
    ];

    protected const METAFIELD_NAMESPACES = [
        'quantity_discount' => '$app:quantity-discount',
        'bogo' => '$app:bogo-discount',
        'free_gift' => '$app:free-gift-discount',
        'bundle' => '$app:bundle-discount',
    ];

    public function sync(Shop $shop, FeatureConfig $config): void
    {
        match ($config->type) {
            'quantity_discount' => $this->syncDiscount($shop, $config, $this->quantityDiscountPayload($config)),
            'bogo' => $this->syncDiscount($shop, $config, $this->bogoPayload($config)),
            'free_gift' => $this->syncDiscount($shop, $config, $this->freeGiftPayload($config)),
            'bundle' => $this->syncBundle($shop, $config),
            default => null,
        };
    }

    public function remove(Shop $shop, FeatureConfig $config): void
    {
        $discountId = $config->shopify_ref_ids['discount_id'] ?? null;
        if (! $discountId) {
            return;
        }

        $client = new ShopifyApiClient($shop);
        $client->graphql(<<<'GQL'
            mutation discountAutomaticDelete($id: ID!) {
                discountAutomaticDelete(id: $id) {
                    deletedAutomaticDiscountId
                    userErrors { field message }
                }
            }
            GQL, ['id' => $discountId]);
    }

    protected function quantityDiscountPayload(FeatureConfig $config): array
    {
        return [
            'tiers' => $config->settings['tiers'] ?? [],
            'productIds' => $config->settings['product_ids'] ?? [],
            'message' => $config->name ?? 'Quantity discount',
        ];
    }

    protected function bogoPayload(FeatureConfig $config): array
    {
        return [
            'buyQuantity' => $config->settings['buy_quantity'] ?? null,
            'buyProductIds' => $config->settings['buy_product_ids'] ?? [],
            'getProductIds' => $config->settings['get_product_ids'] ?? [],
            'getDiscountPercentage' => $config->settings['get_discount_percentage'] ?? 100,
            'maxRewardsPerOrder' => $config->settings['max_rewards_per_order'] ?? null,
            'message' => $config->name ?? 'Buy X get Y',
        ];
    }

    protected function freeGiftPayload(FeatureConfig $config): array
    {
        return [
            'trigger' => $config->settings['trigger'] ?? null,
            'giftVariantId' => $config->settings['gift_variant_id'] ?? null,
            'discountPercentage' => $config->settings['discount_percentage'] ?? 100,
            'message' => $config->name ?? 'Free gift',
        ];
    }

    /**
     * discountAutomaticAppCreate/Update: creates or updates a shop-wide
     * automatic discount that runs our Function, with the config JSON set
     * directly on the function-configuration metafield at creation time.
     */
    protected function syncDiscount(Shop $shop, FeatureConfig $config, array $payload): void
    {
        $functionId = $this->resolveFunctionId($shop, self::FUNCTION_TITLES[$config->type]);
        if (! $functionId) {
            throw new \RuntimeException("Could not resolve deployed function id for '{$config->type}'. Has `shopify app deploy` run?");
        }

        $client = new ShopifyApiClient($shop);
        $existingId = $config->shopify_ref_ids['discount_id'] ?? null;

        $metafields = [[
            'namespace' => self::METAFIELD_NAMESPACES[$config->type],
            'key' => 'function-configuration',
            'type' => 'json',
            'value' => json_encode($payload),
        ]];

        if ($existingId) {
            $result = $client->graphql(<<<'GQL'
                mutation discountAutomaticAppUpdate($id: ID!, $automaticAppDiscount: DiscountAutomaticAppInput!) {
                    discountAutomaticAppUpdate(id: $id, automaticAppDiscount: $automaticAppDiscount) {
                        automaticAppDiscount { discountId }
                        userErrors { field message }
                    }
                }
                GQL, [
                'id' => $existingId,
                'automaticAppDiscount' => [
                    'title' => $config->name ?? $config->type,
                    'metafields' => $metafields,
                ],
            ])->json('data.discountAutomaticAppUpdate');
        } else {
            $result = $client->graphql(<<<'GQL'
                mutation discountAutomaticAppCreate($automaticAppDiscount: DiscountAutomaticAppInput!) {
                    discountAutomaticAppCreate(automaticAppDiscount: $automaticAppDiscount) {
                        automaticAppDiscount { discountId }
                        userErrors { field message }
                    }
                }
                GQL, [
                'automaticAppDiscount' => [
                    'title' => $config->name ?? $config->type,
                    'functionId' => $functionId,
                    'startsAt' => now()->toIso8601String(),
                    'metafields' => $metafields,
                ],
            ])->json('data.discountAutomaticAppCreate');
        }

        $key = $existingId ? 'discountAutomaticAppUpdate' : 'discountAutomaticAppCreate';
        if (! empty($result['userErrors'])) {
            throw new \RuntimeException(collect($result['userErrors'])->pluck('message')->implode('; '));
        }

        $discountId = $result['automaticAppDiscount']['discountId'] ?? $existingId;
        $config->update(['shopify_ref_ids' => array_merge($config->shopify_ref_ids ?? [], ['discount_id' => $discountId])]);
    }

    /**
     * Bundles don't go through discountAutomaticAppCreate directly for the
     * expand step -- the bundle-transform Cart Transform function reads
     * component config from a PRODUCT metafield (not the discount node),
     * so the bundle's parent product needs that metafield written, plus a
     * separate bundle-discount automatic discount for the price break.
     */
    protected function syncBundle(Shop $shop, FeatureConfig $config): void
    {
        $bundleProductId = $config->settings['bundle_product_id'] ?? null;
        $components = $config->settings['components'] ?? [];

        if (! $bundleProductId || empty($components)) {
            throw new \RuntimeException('Bundle config is missing bundle_product_id or components.');
        }

        $client = new ShopifyApiClient($shop);

        $client->graphql(<<<'GQL'
            mutation metafieldsSet($metafields: [MetafieldsSetInput!]!) {
                metafieldsSet(metafields: $metafields) {
                    userErrors { field message }
                }
            }
            GQL, [
            'metafields' => [[
                'ownerId' => $bundleProductId,
                'namespace' => '$app:bundle-components',
                'key' => 'components',
                'type' => 'json',
                'value' => json_encode($components),
            ]],
        ]);

        if (($config->settings['discount_type'] ?? null) && ($config->settings['discount_value'] ?? null)) {
            $this->syncDiscount($shop, $config, [
                'components' => $components,
                'discountType' => $config->settings['discount_type'],
                'discountValue' => $config->settings['discount_value'],
                'message' => $config->name ?? 'Bundle discount',
            ]);
        }
    }

    protected function resolveFunctionId(Shop $shop, string $title): ?string
    {
        $cacheKey = "shopify_function_id:{$shop->id}:{$title}";

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $client = new ShopifyApiClient($shop);
        $nodes = $client->graphql(<<<'GQL'
            query {
                shopifyFunctions(first: 25) {
                    nodes { id title apiType }
                }
            }
            GQL)->json('data.shopifyFunctions.nodes') ?? [];

        $id = null;
        foreach ($nodes as $node) {
            if ($node['title'] === $title) {
                $id = $node['id'];
                break;
            }
        }

        if ($id !== null) {
            Cache::put($cacheKey, $id, now()->addHours(6));
        } else {
            Log::warning('Could not resolve Shopify function id', [
                'shop' => $shop->domain,
                'title' => $title,
                'available_titles' => array_column($nodes, 'title'),
            ]);
        }

        return $id;
    }
}
