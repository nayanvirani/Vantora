<?php

namespace App\Http\Controllers;

use App\Models\FeatureConfig;
use App\Services\PlanGateService;
use App\Services\Shopify\ShopifyApiClient;
use Illuminate\Http\Request;

/**
 * F-18 checkout-stage Bundle upsell, called by
 * extensions/checkout-bundle-upsell. Authenticated via
 * VerifyShopifyExtensionSessionToken, same pattern as ThankYouController.
 *
 * Gated by PlanGateService::canAccessModule('checkout_extension') --
 * wires up the pro_only_modules entry that existed in config/shopify.php
 * with nothing calling it yet. Returns no bundle data (not just a
 * hidden button) when not Pro, since there's no proven client-side way
 * for the extension itself to know the shop's plan.
 *
 * Matching is deliberately simple: the first active Bundle config whose
 * bundle variant isn't already in the cart. No relevance scoring against
 * what's actually in the cart -- that's a v2 concern, not this one.
 *
 * UNTESTED: no dev store in this environment to verify the extension's
 * network calls actually reach this route with the expected shape.
 */
class CheckoutUpsellController extends Controller
{
    public function data(Request $request)
    {
        $shop = $request->attributes->get('shop');

        if (! app(PlanGateService::class)->canAccessModule($shop, 'checkout_extension')) {
            return response()->json(null, 404);
        }

        $data = $request->validate([
            'cart_line_product_ids' => ['nullable', 'array'],
            'cart_line_product_ids.*' => ['string'],
        ]);
        $cartProductIds = $data['cart_line_product_ids'] ?? [];

        $bundles = FeatureConfig::query()
            ->where('shop_id', $shop->id)
            ->where('type', 'bundle')
            ->where('status', 'active')
            ->get();

        if ($bundles->isEmpty()) {
            return response()->json(null);
        }

        $bundleProductIds = $bundles->map(fn ($b) => $b->settings['bundle_product_id'] ?? null)->filter()->values()->all();

        if (empty($bundleProductIds)) {
            return response()->json(null);
        }

        $client = new ShopifyApiClient($shop);
        $nodes = $client->graphql(<<<'GQL'
            query bundlePickers($ids: [ID!]!) {
                nodes(ids: $ids) {
                    id
                    ... on Product {
                        metafield(namespace: "vantora", key: "bundle_picker") { value }
                    }
                }
            }
            GQL, ['ids' => $bundleProductIds])->json('data.nodes') ?? [];

        $pickersByProductId = collect($nodes)->filter()->keyBy('id');

        foreach ($bundles as $bundle) {
            $productId = $bundle->settings['bundle_product_id'] ?? null;
            $node = $productId ? $pickersByProductId->get($productId) : null;
            $picker = $node && $node['metafield'] ? json_decode($node['metafield']['value'], true) : null;

            if (! $picker || empty($picker['bundle_variant_id'])) {
                continue;
            }

            if (in_array($productId, $cartProductIds, true)) {
                continue;
            }

            return response()->json([
                'heading' => $picker['heading'] ?? 'Bundle & Save',
                'message' => $picker['message'] ?? null,
                'bundle_variant_id' => $picker['bundle_variant_id'],
                'components' => $picker['components'] ?? [],
                'regular_total' => $picker['regular_total'] ?? null,
                'bundle_price' => $picker['bundle_price'] ?? null,
                'savings_percent' => $picker['savings_percent'] ?? 0,
            ]);
        }

        return response()->json(null);
    }
}
