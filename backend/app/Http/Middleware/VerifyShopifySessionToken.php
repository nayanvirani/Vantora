<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use App\Services\Shopify\ShopProvisioningService;
use App\Services\Shopify\ShopifyAuthService;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the session token App Bridge attaches as a Bearer token on every
 * embedded-app API request, per Shopify's session token auth model.
 *
 * Because shopify.app.toml has no use_legacy_install_flow = true, Shopify
 * grants scopes and embeds the app itself ("managed installation") without
 * ever calling /auth/callback -- the first this app hears about a shop is a
 * session token on a request exactly like this one. So on first contact
 * with an unknown (or previously uninstalled/tokenless) shop, this
 * middleware performs Token Exchange itself to obtain an offline access
 * token and provisions the Shop record right here, instead of 401ing and
 * leaving the embedded app permanently stuck on "Unknown or uninstalled
 * shop" for every real install.
 */
class VerifyShopifySessionToken
{
    public function __construct(
        protected ShopifyAuthService $auth,
        protected ShopProvisioningService $provisioning,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        abort_if(! $token, 401, 'Missing session token.');

        try {
            $claims = JWT::decode($token, new Key(config('shopify.api_secret'), 'HS256'));
        } catch (\Throwable $e) {
            abort(401, 'Invalid session token.');
        }

        if (($claims->aud ?? null) !== config('shopify.api_key')) {
            abort(401, 'Session token audience mismatch.');
        }

        $shopDomain = parse_url($claims->dest ?? '', PHP_URL_HOST);

        abort_if(! $shopDomain, 401, 'Session token missing dest claim.');

        $shop = Shop::query()->where('domain', $shopDomain)->first();

        if (! $shop || $shop->uninstalled_at || ! $shop->access_token) {
            $shop = $this->provisionViaTokenExchange($shopDomain, $token);
        }

        $request->attributes->set('shop', $shop);

        return $next($request);
    }

    protected function provisionViaTokenExchange(string $shopDomain, string $sessionToken): Shop
    {
        try {
            $tokenData = $this->auth->exchangeSessionTokenForOfflineToken($shopDomain, $sessionToken);
        } catch (\Throwable $e) {
            Log::error('Shopify token exchange failed', ['shop' => $shopDomain, 'error' => $e->getMessage()]);
            abort(401, 'Shop not installed.');
        }

        $shop = Shop::query()->updateOrCreate(
            ['domain' => $shopDomain],
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

        $this->provisioning->registerWebhooks($shop);
        $this->provisioning->syncShopDetails($shop);

        return $shop;
    }
}
