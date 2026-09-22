<?php

namespace App\Services\Shopify;

use App\Models\FeatureConfig;
use App\Models\Shop;

/**
 * Bridges feature_configs.settings (set via the admin GUI) to the actual
 * storefront: writes a shop-level metafield the theme app extension's
 * Liquid blocks read, falling back to their own Theme Editor block
 * settings when the metafield isn't present yet. Closes the gap where the
 * GUI settings screens saved data that never reached the live storefront
 * (a merchant reported this directly -- colors/text set in the admin had
 * no effect on the store).
 *
 * Uses an explicit "vantora" namespace rather than the `$app` reserved
 * shorthand: writing with `$app` is well-documented, but how it resolves
 * for *reading back in Liquid* (bracket notation? a resolved
 * `app--{client_id}` namespace?) isn't clearly documented anywhere
 * checked, and guessing wrong would make this whole sync silently do
 * nothing -- exactly the bug being fixed. An explicit namespace is
 * unambiguous on both ends: `shop.metafields.vantora.<key>` in Liquid,
 * matching `namespace: "vantora"` here. Writing a shop-level metafield
 * needs no scope beyond what mutating the Shop resource itself needs
 * (confirmed against shopify.dev's metafieldsSet docs) -- already proven
 * working since ShopProvisioningService successfully reads
 * `shop { email }` today with the same access token.
 */
class ThemeSettingsSyncService
{
    /** feature_config type -> metafield key. Only types a Liquid block actually reads live here. */
    protected const METAFIELD_KEYS = [
        'sticky_atc' => 'sticky_atc',
        'shipping_bar' => 'shipping_bar',
        'trust_badges' => 'trust_badges',
        'faq' => 'faq',
        'goal_tracker' => 'goal_tracker',
        'quantity_discount' => 'quantity_discount',
    ];

    /**
     * Types whose Liquid block also checks a *product*-level metafield
     * before the shop-level one (spec F-13/F-14: "product/collection
     * targeting") -- a config with settings.target_product_ids writes to
     * each of those products instead of the shop, so it only applies
     * there; a config with no targeting still writes shop-wide as before.
     *
     * quantity_discount reuses this same product-vs-shop resolve order for
     * its new storefront tier-table block, targeted via settings.product_ids
     * (the field its Discount Function payload already uses to scope which
     * products the discount applies to -- see targetProductIds() below).
     */
    protected const PRODUCT_TARGETABLE_TYPES = ['trust_badges', 'faq', 'quantity_discount'];

    public function syncs(string $type): bool
    {
        return isset(self::METAFIELD_KEYS[$type]);
    }

    public function sync(Shop $shop, FeatureConfig $config): void
    {
        $key = self::METAFIELD_KEYS[$config->type] ?? null;

        if (! $key) {
            return;
        }

        $client = new ShopifyApiClient($shop);
        $productIds = $this->targetProductIds($config);

        if ($productIds) {
            $client->graphql(<<<'GQL'
                mutation metafieldsSet($metafields: [MetafieldsSetInput!]!) {
                    metafieldsSet(metafields: $metafields) {
                        userErrors { field message }
                    }
                }
                GQL, [
                'metafields' => collect($productIds)->map(fn ($productId) => [
                    'ownerId' => $productId,
                    'namespace' => 'vantora',
                    'key' => $key,
                    'type' => 'json',
                    'value' => json_encode($config->settings),
                ])->all(),
            ]);

            return;
        }

        $shopId = $client->graphql('query { shop { id } }')->json('data.shop.id');

        $client->graphql(<<<'GQL'
            mutation metafieldsSet($metafields: [MetafieldsSetInput!]!) {
                metafieldsSet(metafields: $metafields) {
                    userErrors { field message }
                }
            }
            GQL, [
            'metafields' => [[
                'ownerId' => $shopId,
                'namespace' => 'vantora',
                'key' => $key,
                'type' => 'json',
                'value' => json_encode($config->settings),
            ]],
        ]);
    }

    public function remove(Shop $shop, FeatureConfig $config): void
    {
        $key = self::METAFIELD_KEYS[$config->type] ?? null;

        if (! $key) {
            return;
        }

        $client = new ShopifyApiClient($shop);
        $productIds = $this->targetProductIds($config);

        if ($productIds) {
            $client->graphql(<<<'GQL'
                mutation metafieldsDelete($metafields: [MetafieldIdentifierInput!]!) {
                    metafieldsDelete(metafields: $metafields) {
                        userErrors { field message }
                    }
                }
                GQL, [
                'metafields' => collect($productIds)->map(fn ($productId) => [
                    'ownerId' => $productId,
                    'namespace' => 'vantora',
                    'key' => $key,
                ])->all(),
            ]);

            return;
        }

        $shopId = $client->graphql('query { shop { id } }')->json('data.shop.id');

        $client->graphql(<<<'GQL'
            mutation metafieldsDelete($metafields: [MetafieldIdentifierInput!]!) {
                metafieldsDelete(metafields: $metafields) {
                    userErrors { field message }
                }
            }
            GQL, [
            'metafields' => [[
                'ownerId' => $shopId,
                'namespace' => 'vantora',
                'key' => $key,
            ]],
        ]);
    }

    protected function targetProductIds(FeatureConfig $config): array
    {
        if (! in_array($config->type, self::PRODUCT_TARGETABLE_TYPES, true)) {
            return [];
        }

        // trust_badges/faq store targeting under settings.target_product_ids;
        // quantity_discount's field is settings.product_ids (same semantic --
        // "applies to these products, empty = store-wide" -- already used by
        // its Discount Function payload, kept as-is here rather than renamed
        // to avoid orphaning already-saved settings on live configs).
        $ids = $config->settings['target_product_ids'] ?? $config->settings['product_ids'] ?? [];

        return array_values(array_filter($ids));
    }
}
