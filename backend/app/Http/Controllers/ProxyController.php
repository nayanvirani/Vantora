<?php

namespace App\Http\Controllers;

use App\Models\FeatureConfig;
use Illuminate\Http\Request;

/**
 * Storefront-facing endpoints reached through Shopify's App Proxy
 * (/apps/vantora/* on the shop's domain, signature-verified and forwarded
 * here -- see VerifyShopifyAppProxySignature). These serve theme blocks
 * that need live app data without a theme deploy for every config change.
 */
class ProxyController extends Controller
{
    /**
     * F-28 Cart Upsell. Returns the merchant's manual (or, later,
     * AI-suggested) picks for the active cart_upsell config, minus
     * whatever's already in the cart.
     */
    public function recommendations(Request $request)
    {
        $shop = $request->attributes->get('shop');

        $config = FeatureConfig::query()
            ->where('shop_id', $shop->id)
            ->where('type', 'cart_upsell')
            ->where('status', 'active')
            ->first();

        if (! $config) {
            return response()->json(['recommendations' => []]);
        }

        // /cart.js and /cart/add.js speak plain numeric IDs, not Admin API
        // GIDs, so picks are matched/returned in that form regardless of
        // which shape they were saved in.
        $numericId = fn ($id) => is_string($id) && str_contains($id, '/') ? (int) strrchr($id, '/') : $id;

        $excludeProductIds = array_filter(explode(',', (string) $request->query('product_ids', '')));
        $maxItems = (int) ($config->settings['max_items'] ?? 3);

        $picks = collect($config->settings['picks'] ?? [])
            ->reject(fn ($pick) => in_array((string) $numericId($pick['product_id'] ?? null), $excludeProductIds, true))
            ->take($maxItems)
            ->map(fn ($pick) => [
                'title' => $pick['title'] ?? '',
                'price' => $pick['price'] ?? '',
                'image' => $pick['image'] ?? null,
                'variant_id' => $numericId($pick['variant_id'] ?? null),
            ])
            ->values();

        return response()->json(['recommendations' => $picks]);
    }

    /**
     * F-29 Frequently Bought Together. Returns the configured set for the
     * given trigger product, if one exists.
     */
    public function frequentlyBoughtTogether(Request $request)
    {
        $shop = $request->attributes->get('shop');
        $productId = $request->query('product_id');

        $config = FeatureConfig::query()
            ->where('shop_id', $shop->id)
            ->where('type', 'fbt')
            ->where('status', 'active')
            ->first();

        if (! $config || ! $productId) {
            return response()->json(['set' => null]);
        }

        $numericId = fn ($id) => is_string($id) && str_contains($id, '/') ? (int) strrchr($id, '/') : $id;

        $set = collect($config->settings['sets'] ?? [])
            ->first(fn ($set) => (string) $numericId($set['trigger_product_id'] ?? null) === (string) $productId);

        return response()->json(['set' => $set]);
    }
}
