<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\Shopify\ShopifyApiClient;
use App\Services\Shopify\ShopifyAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyAuthController extends Controller
{
    public function __construct(protected ShopifyAuthService $auth)
    {
    }

    /**
     * Entry point Shopify hits (and the App Store "Install" link).
     * Redirects the merchant to Shopify's OAuth consent screen.
     */
    public function install(Request $request)
    {
        $shop = (string) $request->query('shop');

        abort_unless($shop && $this->auth->isValidShopDomain($shop), 400, 'Invalid shop parameter.');

        if ($request->query('hmac') && ! $this->auth->verifyHmac($request->query())) {
            abort(400, 'Invalid HMAC signature.');
        }

        $state = $this->auth->generateState();
        session(['shopify_oauth_state' => $state, 'shopify_oauth_shop' => $shop]);

        return redirect()->away($this->auth->buildInstallUrl($shop, $state));
    }

    /**
     * OAuth callback: exchanges the code for an access token, upserts the
     * shop record, registers webhooks, and hands off to the embedded app.
     */
    public function callback(Request $request)
    {
        $shop = (string) $request->query('shop');
        $code = (string) $request->query('code');
        $state = (string) $request->query('state');

        abort_unless($shop && $this->auth->isValidShopDomain($shop), 400, 'Invalid shop parameter.');
        abort_unless(! empty($code), 400, 'Missing authorization code.');
        abort_unless(
            $state && $state === session('shopify_oauth_state'),
            400,
            'Invalid OAuth state.'
        );
        abort_unless($this->auth->verifyHmac($request->query()), 400, 'Invalid HMAC signature.');

        $tokenData = $this->auth->exchangeCodeForToken($shop, $code);

        $record = Shop::query()->updateOrCreate(
            ['domain' => $shop],
            [
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'access_token_expires_at' => isset($tokenData['expires_in'])
                    ? now()->addSeconds($tokenData['expires_in'])
                    : null,
                'refresh_token_expires_at' => isset($tokenData['refresh_token_expires_in'])
                    ? now()->addSeconds($tokenData['refresh_token_expires_in'])
                    : null,
                'scopes' => explode(',', $tokenData['scope'] ?? ''),
                'installed_at' => now(),
                'uninstalled_at' => null,
            ]
        );

        $this->syncShopDetails($record);
        $this->registerWebhooks($record);

        session()->forget(['shopify_oauth_state', 'shopify_oauth_shop']);

        return redirect()->to("/?shop={$shop}&host=" . $request->query('host'));
    }

    protected function syncShopDetails(Shop $shop): void
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

    protected function registerWebhooks(Shop $shop): void
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
}
