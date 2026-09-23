<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\Subscription;
use App\Models\WebhookEvent;
use App\Services\Shopify\ShopProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One method per Shopify webhook topic (spec section 9). Every method is
 * idempotent via recordOnce() -- Shopify redelivers webhooks on timeout
 * or error, so a repeat delivery must not double-apply an effect (e.g.
 * double-creating a Subscription row).
 */
class WebhookController extends Controller
{
    public function __construct(private readonly ShopProvisioningService $provisioning) {}

    public function appUninstalled(Request $request): JsonResponse
    {
        $shop = $this->shopFromRequest($request);

        if (! $this->recordOnce($request, 'app/uninstalled', $shop)) {
            return response()->json(['ok' => true]);
        }

        $shop?->update(['uninstalled_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function appSubscriptionsUpdate(Request $request): JsonResponse
    {
        $shop = $this->shopFromRequest($request);

        if (! $shop || ! $this->recordOnce($request, 'app_subscriptions/update', $shop)) {
            return response()->json(['ok' => true]);
        }

        $payload = $request->json('app_subscription', []);
        $shopifyPlanName = $payload['name'] ?? null;
        $status = strtolower((string) ($payload['status'] ?? ''));

        $internalPlan = $shopifyPlanName
            ? collect(config('shopify.plans'))->search(fn (array $plan) => strcasecmp($plan['name'], $shopifyPlanName) === 0)
            : false;

        if ($internalPlan === false) {
            return response()->json(['ok' => true]);
        }

        Subscription::create([
            'shop_id' => $shop->id,
            'plan' => $internalPlan,
            'shopify_charge_id' => $payload['admin_graphql_api_id'] ?? null,
            'status' => $status === 'active' ? 'active' : $status,
            'trial_ends_at' => $payload['trial_ends_on'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }

    public function shopUpdate(Request $request): JsonResponse
    {
        $shop = $this->shopFromRequest($request);

        if (! $shop || ! $this->recordOnce($request, 'shop/update', $shop)) {
            return response()->json(['ok' => true]);
        }

        $this->provisioning->refreshPlanEligibility($shop);

        return response()->json(['ok' => true]);
    }

    public function themesPublish(Request $request): JsonResponse
    {
        $shop = $this->shopFromRequest($request);

        if (! $shop || ! $this->recordOnce($request, 'themes/publish', $shop)) {
            return response()->json(['ok' => true]);
        }

        $themeId = $request->json('id');
        if ($themeId) {
            $shop->update(['theme_id' => $themeId]);
        }

        return response()->json(['ok' => true]);
    }

    public function themesUpdate(Request $request): JsonResponse
    {
        $shop = $this->shopFromRequest($request);
        $this->recordOnce($request, 'themes/update', $shop);

        // Theme compatibility re-checking is part of the audit engine
        // (Phase 2) -- this topic is registered now (spec section 11) so
        // it's already flowing by the time Phase 2 needs it.
        return response()->json(['ok' => true]);
    }

    public function productsUpdate(Request $request): JsonResponse
    {
        $shop = $this->shopFromRequest($request);
        $this->recordOnce($request, 'products/update', $shop);

        // Consumed by the audit engine (Phase 2) and analytics (Phase 4).
        return response()->json(['ok' => true]);
    }

    public function productsDelete(Request $request): JsonResponse
    {
        $shop = $this->shopFromRequest($request);
        $this->recordOnce($request, 'products/delete', $shop);

        return response()->json(['ok' => true]);
    }

    public function ordersCreate(Request $request): JsonResponse
    {
        $shop = $this->shopFromRequest($request);
        $this->recordOnce($request, 'orders/create', $shop);

        // Consumed by conversion tracking / analytics (Phase 4).
        return response()->json(['ok' => true]);
    }

    /**
     * GDPR mandatory compliance webhook. No customer-level data is stored
     * anywhere in this app yet (Phase 1 only stores shop-level records),
     * so there is nothing to return/redact for an individual customer --
     * acknowledging is the correct, complete response at this phase.
     */
    public function customersDataRequest(Request $request): JsonResponse
    {
        $shop = $this->shopFromRequest($request);
        $this->recordOnce($request, 'customers/data_request', $shop);

        return response()->json(['ok' => true]);
    }

    public function customersRedact(Request $request): JsonResponse
    {
        $shop = $this->shopFromRequest($request);
        $this->recordOnce($request, 'customers/redact', $shop);

        return response()->json(['ok' => true]);
    }

    /**
     * Sent ~48 hours after uninstall. This IS real compliance action for
     * data this app does store: purge the shop's access token and mark it
     * fully erased, rather than just leaving uninstalled_at set.
     */
    public function shopRedact(Request $request): JsonResponse
    {
        $shop = $this->shopFromRequest($request);

        if (! $shop || ! $this->recordOnce($request, 'shop/redact', $shop)) {
            return response()->json(['ok' => true]);
        }

        $shop->update(['access_token' => null]);

        return response()->json(['ok' => true]);
    }

    private function shopFromRequest(Request $request): ?Shop
    {
        $domain = $request->header('X-Shopify-Shop-Domain');

        return $domain ? Shop::where('domain', $domain)->first() : null;
    }

    /**
     * Records this delivery and returns true the first time a given
     * (topic, payload) is seen, false on a redelivery -- callers use the
     * return value to skip re-applying the webhook's effect.
     */
    private function recordOnce(Request $request, string $topic, ?Shop $shop): bool
    {
        $hash = hash('sha256', $request->getContent());

        $event = WebhookEvent::firstOrCreate(
            ['topic' => $topic, 'payload_hash' => $hash],
            ['shop_id' => $shop?->id, 'processed_at' => now()]
        );

        return $event->wasRecentlyCreated;
    }
}
