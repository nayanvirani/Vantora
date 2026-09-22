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
