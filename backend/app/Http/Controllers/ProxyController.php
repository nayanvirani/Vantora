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
     * F-29 Frequently Bought Together. One feature_config per trigger
     * product (a merchant naturally wants a different set per product
     * page) rather than one config holding multiple sets -- matches how
     * every other feature type works (Pro's "unlimited configurations" is
     * already multiple FeatureConfig rows of the same type). Returns the
     * one whose settings.trigger_product_id matches the requested product.
     */
    public function frequentlyBoughtTogether(Request $request)
    {
        $shop = $request->attributes->get('shop');
        $productId = $request->query('product_id');

        if (! $productId) {
            return response()->json(['set' => null]);
        }

        $numericId = fn ($id) => is_string($id) && str_contains($id, '/') ? (int) strrchr($id, '/') : $id;

        $config = FeatureConfig::query()
            ->where('shop_id', $shop->id)
            ->where('type', 'fbt')
            ->where('status', 'active')
            ->get()
            ->first(fn ($config) => (string) $numericId($config->settings['trigger_product_id'] ?? null) === (string) $productId);

        return response()->json(['set' => $config?->settings]);
    }

    /**
     * F-15 Free Gift. Discount Functions can't add cart lines (see
     * free-gift-discount/src/run.js), so the storefront is responsible for
     * adding the gift variant to the cart once its trigger is met, and for
     * keeping it at exactly one unit and present for as long as the trigger
     * stays met -- this endpoint is what tells the storefront block which
     * gift(s) to watch for. A shop can run more than one free-gift rule at
     * once (e.g. spend $100 get gift A, buy product X get gift B), so, like
     * frequentlyBoughtTogether, this returns every active config rather
     * than assuming one.
     */
    public function freeGifts(Request $request)
    {
        $shop = $request->attributes->get('shop');

        $numericId = fn ($id) => is_string($id) && str_contains($id, '/') ? (int) strrchr($id, '/') : $id;

        $offers = FeatureConfig::query()
            ->where('shop_id', $shop->id)
            ->where('type', 'free_gift')
            ->where('status', 'active')
            ->get()
            ->map(function ($config) use ($numericId) {
                $trigger = $config->settings['trigger'] ?? [];
                if (($trigger['type'] ?? null) === 'product') {
                    $trigger['productId'] = $numericId($trigger['productId'] ?? null);
                }

                return [
                    'id' => $config->id,
                    'trigger' => $trigger,
                    'gift_variant_id' => $numericId($config->settings['gift_variant_id'] ?? null),
                    'message' => $config->settings['message'] ?? "You've earned a free gift!",
                ];
            })
            ->filter(fn ($offer) => $offer['gift_variant_id'] && ($offer['trigger']['type'] ?? null))
            ->values();

        return response()->json(['offers' => $offers]);
    }
}
