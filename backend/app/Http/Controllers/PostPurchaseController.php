<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\Shop;
use Illuminate\Http\Request;

/**
 * F-33 Post-purchase One-Click Upsell backend. Called directly by
 * extensions/post-purchase-upsell (sandboxed, like the web pixel -- no
 * session token or app-proxy signature available there).
 *
 * UNTESTED: this extension type needs Shopify's post-purchase access grant
 * (beta, access-by-request) which this environment doesn't have. The
 * request shape assumed here (shop/reference_id/total_price) matches the
 * documented `inputData.initialPurchase` fields but hasn't been exercised
 * against a real checkout.
 */
class PostPurchaseController extends Controller
{
    public function bestOffer(Request $request)
    {
        $data = $request->validate([
            'shop' => ['required', 'string'],
            'reference_id' => ['nullable', 'string'],
            'total_price' => ['nullable', 'numeric'],
        ]);

        $shop = Shop::query()->where('domain', $data['shop'])->whereNull('uninstalled_at')->first();
        if (! $shop || $shop->currentPlan() !== 'pro') {
            return response()->json(null, 404);
        }

        $offer = Offer::query()
            ->where('shop_id', $shop->id)
            ->where('surface', 'post_purchase')
            ->where('status', 'active')
            ->orderBy('chain_order')
            ->get()
            ->first(function ($offer) use ($data) {
                $minCartValue = $offer->trigger['min_cart_value'] ?? null;

                return ! $minCartValue || ($data['total_price'] ?? 0) >= $minCartValue;
            });

        if (! $offer) {
            return response()->json(null, 404);
        }

        return response()->json([
            'id' => $offer->id,
            'variant_id' => $offer->discount['variant_id'] ?? null,
            'title' => $offer->discount['title'] ?? 'Special offer',
            'description' => $offer->discount['description'] ?? null,
            'price' => $offer->discount['price'] ?? null,
            'image' => $offer->discount['image'] ?? null,
            'headline' => $offer->discount['headline'] ?? null,
        ]);
    }
}
