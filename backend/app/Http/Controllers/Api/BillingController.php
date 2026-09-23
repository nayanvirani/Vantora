<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Billing is Shopify Managed Pricing (spec section 3) -- plans, prices
 * and trial length are configured in the Partner Dashboard, this app
 * never calls appSubscriptionCreate. This endpoint just hands the
 * embedded admin SPA the URL to Shopify's own hosted plan-selection page.
 *
 * UNVERIFIED: the exact Managed Pricing URL shape
 * (admin.shopify.com/store/{handle}/charges/{app_handle}/pricing_plans)
 * needs confirming against a live Partner Dashboard app before this is
 * relied on -- flagging rather than asserting it's correct.
 */
class BillingController extends Controller
{
    public function pricingPlansUrl(Request $request): JsonResponse
    {
        $shop = $request->attributes->get('shop');
        $storeHandle = Str::before($shop->domain, '.myshopify.com');
        $appHandle = config('shopify.app_handle');

        return response()->json([
            'url' => "https://admin.shopify.com/store/{$storeHandle}/charges/{$appHandle}/pricing_plans",
        ]);
    }

    /**
     * Display data only (admin-managed Plan rows) for the future embedded
     * billing screen -- Shopify Managed Pricing, not this app, decides
     * what a shop is actually charged and grants.
     */
    public function plans(): JsonResponse
    {
        return response()->json(
            Plan::where('is_active', true)->orderBy('sort_order')->get(['name', 'handle', 'price', 'features'])
        );
    }
}
