<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Shopify\BillingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Billing is Shopify Managed Pricing -- see BillingService. This app never
 * creates a subscription itself; it only tells the embedded frontend
 * whether the paywall should show, and gives it the link to Shopify's own
 * hosted plan page when it does.
 */
class BillingController extends Controller
{
    public function status(Request $request, BillingService $billing): JsonResponse
    {
        $shop = $this->shop($request);
        $subscription = $shop->activeSubscription;

        return response()->json([
            'active' => (bool) $subscription,
            'plan' => $subscription?->plan,
            'manage_plan_url' => $billing->managePlanUrl($shop),
        ]);
    }

    protected function shop(Request $request)
    {
        return $request->attributes->get('shop');
    }
}
