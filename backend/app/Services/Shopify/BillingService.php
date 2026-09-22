<?php

namespace App\Services\Shopify;

use App\Models\Shop;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * Billing is Shopify Managed Pricing: plans, prices and trials live in the
 * Partner Dashboard, and merchants pick a plan on Shopify's own hosted
 * page -- this app never creates a subscription itself. It only builds the
 * link to that hosted page and syncs whatever Shopify reports happened via
 * the app_subscriptions/update webhook (see WebhookController).
 */
class BillingService
{
    /**
     * Shopify's own hosted plan-selection page for this app. There is no
     * confirmation-URL round trip to build here -- the merchant picks a
     * plan entirely on Shopify's side and this app only finds out via the
     * webhook afterward.
     */
    public function managePlanUrl(Shop $shop): string
    {
        $shopHandle = str_replace('.myshopify.com', '', $shop->domain);
        $appHandle = config('shopify.app_handle');

        return "https://admin.shopify.com/store/{$shopHandle}/charges/{$appHandle}/pricing_plans";
    }

    /**
     * Matches the webhook's free-text plan `name` against the `name`
     * configured per plan in config('shopify.plans') -- the only link
     * between "whatever the merchant typed into the Partner Dashboard" and
     * this app's internal starter/pro identifiers. Falls back to null
     * (unrecognized plan) rather than guessing, so a renamed or new Partner
     * Dashboard plan fails loudly instead of silently misgating.
     */
    public function resolvePlanKey(string $shopifyPlanName): ?string
    {
        foreach (config('shopify.plans') as $key => $plan) {
            if (strcasecmp($plan['name'], $shopifyPlanName) === 0) {
                return $key;
            }
        }

        return null;
    }

    public function activateFromWebhook(Shop $shop, array $payload): void
    {
        $subscription = $payload['app_subscription'] ?? $payload;

        $chargeId = (string) ($subscription['admin_graphql_api_id'] ?? '');
        $planName = (string) ($subscription['name'] ?? '');
        $status = strtolower((string) ($subscription['status'] ?? 'pending'));

        if (! $chargeId) {
            return;
        }

        $planKey = $this->resolvePlanKey($planName);

        DB::transaction(function () use ($shop, $chargeId, $planKey, $status) {
            Subscription::query()->updateOrCreate(
                ['shop_id' => $shop->id, 'shopify_charge_id' => $chargeId],
                [
                    // Never overwrite a previously-resolved plan with null
                    // just because this particular payload's name didn't
                    // match -- keep whatever was last known instead.
                    'plan' => $planKey ?? Subscription::query()
                        ->where('shop_id', $shop->id)
                        ->where('shopify_charge_id', $chargeId)
                        ->value('plan') ?? 'starter',
                    'status' => $status,
                ]
            );

            // Shopify only ever has one subscription active per shop --
            // when this one activates, any other row still marked active is
            // a stale prior plan (a switch whose own "cancelled" webhook
            // never arrived, or arrived out of order) and must not keep
            // gating currentPlan()/PlanGateService as if it were current.
            if ($status === 'active') {
                Subscription::query()
                    ->where('shop_id', $shop->id)
                    ->where('shopify_charge_id', '!=', $chargeId)
                    ->where('status', 'active')
                    ->update(['status' => 'cancelled']);
            }
        });
    }
}
