<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlanGateService;
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

    /**
     * Per-feature usage against the Starter plan's 1-per-feature limit
     * (spec section 3: "Show usage in the admin"), plus AI usage -- not
     * shown anywhere in the admin previously.
     */
    public function usage(Request $request, PlanGateService $planGate): JsonResponse
    {
        $shop = $this->shop($request);
        $isPro = $shop->currentPlan() === 'pro';

        $features = collect(config('shopify.gated_feature_types'))
            ->mapWithKeys(fn ($type) => [$type => [
                'used' => $planGate->activeCount($shop, $type),
                'limit' => $isPro ? null : config('shopify.plans.starter.per_feature_limit'),
            ]])
            ->all();

        return response()->json([
            'features' => $features,
            'ai' => [
                'remaining' => $planGate->aiUsageRemaining($shop),
                'cap' => $isPro ? config('shopify.ai_fair_use_cap_per_month') : 1,
            ],
        ]);
    }

    protected function shop(Request $request)
    {
        return $request->attributes->get('shop');
    }
}
