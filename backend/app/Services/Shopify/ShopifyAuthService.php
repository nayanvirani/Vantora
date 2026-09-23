<?php

namespace App\Services\Shopify;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Shopify's standard OAuth install flow: build the authorize redirect,
 * verify the callback's HMAC and state, exchange the code for an access
 * token. See https://shopify.dev/docs/apps/build/authentication-authorization/access-tokens/authorization-code-grant
 */
class ShopifyAuthService
{
    public function isValidShopDomain(string $shop): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-]*\.myshopify\.com$/', $shop);
    }

    public function authorizeUrl(string $shop, string $state, string $redirectUri): string
    {
        $query = http_build_query([
            'client_id' => config('shopify.api_key'),
            'scope' => config('shopify.scopes'),
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);

        return "https://{$shop}/admin/oauth/authorize?{$query}";
    }

    /**
     * Verifies the callback query string's hmac param per Shopify's
     * documented algorithm: every param except hmac itself, sorted by
     * key, joined as key=value pairs with '&', HMAC-SHA256 hex digest.
     *
     * @param  array<string, string>  $query
     */
    public function verifyCallbackHmac(array $query): bool
    {
        $hmac = $query['hmac'] ?? null;
        if (! $hmac) {
            return false;
        }

        $params = $query;
        unset($params['hmac']);
        ksort($params);

        $computed = hash_hmac('sha256', http_build_query($params), (string) config('shopify.api_secret'));

        return hash_equals($computed, $hmac);
    }

    /**
     * @return array{access_token: string, scope: string}
     */
    public function exchangeCodeForToken(string $shop, string $code): array
    {
        $response = Http::asJson()
            ->post("https://{$shop}/admin/oauth/access_token", [
                'client_id' => config('shopify.api_key'),
                'client_secret' => config('shopify.api_secret'),
                'code' => $code,
            ])
            ->throw();

        return $response->json();
    }

    public function generateState(): string
    {
        return Str::random(40);
    }
}
