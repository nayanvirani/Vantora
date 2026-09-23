<?php

namespace App\Services\Shopify;

use App\Models\Shop;
use App\Models\Subscription;

/**
 * Billing is Shopify Managed Pricing (spec section 3) -- plans/prices/
 * trial are configured in the Partner Dashboard, this app never calls
 * appSubscriptionCreate. This service only ever mirrors what Shopify
 * reports, two ways:
 *  - applyFromWebhook(): the normal path, driven by app_subscriptions/update.
 *  - syncActivePlanViaApi(): a manual safety-net re-query (GraphQL
 *    currentAppInstallation.activeSubscriptions) for the rare case a
 *    webhook was missed or delayed -- exposed as a "Resync from Shopify"
 *    button on the shop detail admin screen.
 */
class BillingService
{
    /**
     * Matches a Shopify plan name string (from a webhook payload or a
     * GraphQL activeSubscriptions query) back to this app's internal
     * plan key ('starter'/'pro'), per config('shopify.plans').*.name.
     */
    public function resolveInternalPlan(?string $shopifyPlanName): string|false
    {
        if (! $shopifyPlanName) {
            return false;
        }

        return collect(config('shopify.plans'))
            ->search(fn (array $plan) => strcasecmp($plan['name'], $shopifyPlanName) === 0);
    }

    public function applyFromWebhook(Shop $shop, array $payload): void
    {
        $internalPlan = $this->resolveInternalPlan($payload['name'] ?? null);

        if ($internalPlan === false) {
            return;
        }

        Subscription::create([
            'shop_id' => $shop->id,
            'plan' => $internalPlan,
            'shopify_charge_id' => $payload['admin_graphql_api_id'] ?? null,
            'status' => strtolower((string) ($payload['status'] ?? '')) === 'active' ? 'active' : strtolower((string) ($payload['status'] ?? '')),
            'trial_ends_at' => $payload['trial_ends_on'] ?? null,
        ]);
    }

    /**
     * @return bool whether a new Subscription row was created (i.e. the
     *              locally-known state was actually out of sync).
     */
    public function syncActivePlanViaApi(Shop $shop): bool
    {
        $client = new ShopifyApiClient($shop);

        $response = $client->graphql(<<<'GQL'
            query {
                currentAppInstallation {
                    activeSubscriptions {
                        id
                        name
                        status
                        currentPeriodEnd
                    }
                }
            }
            GQL);

        $active = collect($response->json('data.currentAppInstallation.activeSubscriptions'))
            ->first(fn (array $sub) => strtolower($sub['status']) === 'active');

        if (! $active) {
            return false;
        }

        $internalPlan = $this->resolveInternalPlan($active['name']);
        if ($internalPlan === false) {
            return false;
        }

        $current = $shop->subscriptions()->where('status', 'active')->latest('id')->first();

        if ($current && $current->plan === $internalPlan && $current->shopify_charge_id === $active['id']) {
            return false;
        }

        $current?->update(['status' => 'superseded']);

        Subscription::create([
            'shop_id' => $shop->id,
            'plan' => $internalPlan,
            'shopify_charge_id' => $active['id'],
            'status' => 'active',
        ]);

        return true;
    }
}
