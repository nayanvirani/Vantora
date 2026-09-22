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
        'cart_upsell' => 'cart_upsell',
    ];

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
}
