<?php

namespace App\Services\Shopify;

use App\Models\Shop;
use App\Models\Subscription;

/**
 * Wraps the Shopify Billing API (appSubscriptionCreate) so a plan choice
 * turns into a Shopify-hosted confirmation page and, once confirmed via
 * the app_subscriptions/update webhook, an active local subscription.
 */
class BillingService
{
    protected const MUTATION = <<<'GQL'
        mutation AppSubscriptionCreate(
            $name: String!
            $returnUrl: URL!
            $trialDays: Int!
            $price: Decimal!
        ) {
            appSubscriptionCreate(
                name: $name
                returnUrl: $returnUrl
                trialDays: $trialDays
                test: false
                lineItems: [
                    {
                        plan: {
                            appRecurringPricingDetails: {
                                price: { amount: $price, currencyCode: USD }
                                interval: EVERY_30_DAYS
                            }
                        }
                    }
                ]
            ) {
                confirmationUrl
                appSubscription {
                    id
                    status
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GQL;

    public function startSubscription(Shop $shop, string $plan, string $returnUrl): array
    {
        $planConfig = config("shopify.plans.{$plan}");

        $client = new ShopifyApiClient($shop);

        $response = $client->graphql(self::MUTATION, [
            'name' => "Vantora {$planConfig['name']}",
            'returnUrl' => $returnUrl,
            'trialDays' => $planConfig['trial_days'],
            'price' => (string) $planConfig['price'],
        ])->json('data.appSubscriptionCreate');

        if (! empty($response['userErrors'])) {
            throw new \RuntimeException(collect($response['userErrors'])->pluck('message')->implode('; '));
        }

        Subscription::query()->create([
            'shop_id' => $shop->id,
            'plan' => $plan,
            'shopify_charge_id' => $response['appSubscription']['id'] ?? null,
            'status' => 'pending',
            'trial_ends_at' => now()->addDays($planConfig['trial_days']),
        ]);

        return $response;
    }

    public function activateFromWebhook(Shop $shop, array $payload): void
    {
        $chargeId = $payload['app_subscription']['admin_graphql_api_id'] ?? null;
        $status = strtolower($payload['app_subscription']['status'] ?? '');

        if (! $chargeId) {
            return;
        }

        Subscription::query()
            ->where('shop_id', $shop->id)
            ->where('shopify_charge_id', $chargeId)
            ->update(['status' => $status]);
    }
}
