<?php

namespace App\Services\Shopify;

use App\Models\Shop;
use Illuminate\Support\Facades\Log;

/**
 * Everything that has to happen once this app has a usable access token for
 * a shop, regardless of how that token was obtained: sync display details
 * (name/email/plan) and register the webhook topics this app depends on.
 * Shared by both the classic OAuth callback (ShopifyAuthController, kept as
 * a fallback/manual-install path) and Token Exchange auto-provisioning
 * (VerifyShopifySessionToken, the path Shopify's managed installation
 * actually uses) so the two don't duplicate or drift apart.
 */
class ShopProvisioningService
{
    public function syncShopDetails(Shop $shop): void
    {
        try {
            $client = new ShopifyApiClient($shop);

            $data = $client->graphql(<<<'GQL'
                query {
                    shop {
                        email
                        plan { displayName partnerDevelopment shopifyPlus }
                    }
                }
                GQL)->json('data.shop');

            $shop->update([
                'email' => $data['email'] ?? null,
                'shopify_plan' => $data['plan']['displayName'] ?? null,
                'is_plus' => (bool) ($data['plan']['shopifyPlus'] ?? false),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to sync shop details', ['shop' => $shop->domain, 'error' => $e->getMessage()]);
        }
    }

    public function registerWebhooks(Shop $shop): void
    {
        $topics = [
            'APP_UNINSTALLED' => '/webhooks/shopify/app-uninstalled',
            'APP_SUBSCRIPTIONS_UPDATE' => '/webhooks/shopify/app-subscriptions-update',
            'SHOP_UPDATE' => '/webhooks/shopify/shop-update',
            'THEMES_PUBLISH' => '/webhooks/shopify/themes-publish',
            'THEMES_UPDATE' => '/webhooks/shopify/themes-update',
            'PRODUCTS_UPDATE' => '/webhooks/shopify/products-update',
            'PRODUCTS_DELETE' => '/webhooks/shopify/products-delete',
            'ORDERS_CREATE' => '/webhooks/shopify/orders-create',
        ];

        $client = new ShopifyApiClient($shop);
        $callbackBase = rtrim(config('app.url'), '/');

        foreach ($topics as $topic => $path) {
            try {
                $client->graphql(<<<'GQL'
                    mutation webhookSubscriptionCreate($topic: WebhookSubscriptionTopic!, $webhookSubscription: WebhookSubscriptionInput!) {
                        webhookSubscriptionCreate(topic: $topic, webhookSubscription: $webhookSubscription) {
                            webhookSubscription { id }
                            userErrors { field message }
                        }
                    }
                    GQL, [
                    'topic' => $topic,
                    'webhookSubscription' => [
                        'callbackUrl' => $callbackBase . $path,
                        'format' => 'JSON',
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to register webhook', ['topic' => $topic, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Deploying extensions/vantora-pixel doesn't make it collect anything --
     * a Web Pixel extension only starts running once a WebPixel record
     * exists for the shop (webPixelCreate), which is what makes it show up
     * under Settings > Customer events > Pixels; a merchant found it
     * missing there and reported it. `accountID` is a required field on
     * the extension's settings schema but unused by src/index.js (it
     * reads the shop domain from the pixel's own init data instead), so
     * any non-empty value satisfies validation.
     */
    public function connectWebPixel(Shop $shop): void
    {
        try {
            $client = new ShopifyApiClient($shop);

            // Check first rather than trying to create and treating a
            // "duplicate" userError as success -- the exact error code
            // webPixelCreate returns when one already exists isn't
            // documented, so this avoids relying on a guessed string.
            $existing = $client->graphql('query { webPixel { id } }')->json('data.webPixel.id');

            if ($existing) {
                return;
            }

            $result = $client->graphql(<<<'GQL'
                mutation webPixelCreate($webPixel: WebPixelInput!) {
                    webPixelCreate(webPixel: $webPixel) {
                        webPixel { id }
                        userErrors { field message code }
                    }
                }
                GQL, [
                'webPixel' => [
                    'settings' => json_encode(['accountID' => $shop->domain]),
                ],
            ]);

            $errors = $result->json('data.webPixelCreate.userErrors') ?? [];

            if ($errors) {
                Log::warning('Failed to connect web pixel', ['shop' => $shop->domain, 'errors' => $errors]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to connect web pixel', ['shop' => $shop->domain, 'error' => $e->getMessage()]);
        }
    }
}
