<?php

namespace App\Services\Shopify;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ShopifyAuthService
{
    public function buildInstallUrl(string $shop, string $state): string
    {
        $query = http_build_query([
            'client_id' => config('shopify.api_key'),
            'scope' => config('shopify.scopes'),
            'redirect_uri' => route('shopify.auth.callback'),
            'state' => $state,
            'grant_options[]' => '',
        ]);

        return "https://{$shop}/admin/oauth/authorize?{$query}";
    }

    /**
     * Verify the HMAC signature Shopify attaches to OAuth and proxy requests.
     */
    public function verifyHmac(array $params): bool
    {
        $hmac = $params['hmac'] ?? null;

        if (! $hmac) {
            return false;
        }

        unset($params['hmac'], $params['signature']);

        ksort($params);

        $computed = hash_hmac('sha256', http_build_query($params), config('shopify.api_secret'));

        return hash_equals($computed, $hmac);
    }

    public function isValidShopDomain(string $shop): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-]*\.myshopify\.com$/', $shop);
    }

    /**
     * `expiring=1` is required, not optional, for a new public app: Shopify
     * no longer permits Admin API GraphQL requests from non-expiring
     * offline tokens for apps created after its cutover (verified against
     * shopify.dev's authorization-code-grant docs, 2026-09-22). Returns a
     * 1-hour access_token plus a 90-day refresh_token instead of the
     * permanent token the classic (non-expiring) flow used to return.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, refresh_token_expires_in: int, scope: string}
     */
    public function exchangeCodeForToken(string $shop, string $code): array
    {
        $response = Http::asJson()->post("https://{$shop}/admin/oauth/access_token", [
            'client_id' => config('shopify.api_key'),
            'client_secret' => config('shopify.api_secret'),
            'code' => $code,
            'expiring' => 1,
        ])->throw();

        return $response->json();
    }

    /**
     * Trades a still-valid refresh_token for a new access/refresh token
     * pair. Shopify issues a new refresh_token on every use -- the old one
     * is consumed and the caller must persist the new one, not reuse it.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, refresh_token_expires_in: int, scope: string}
     */
    public function refreshAccessToken(string $shop, string $refreshToken): array
    {
        $response = Http::asJson()->post("https://{$shop}/admin/oauth/access_token", [
            'client_id' => config('shopify.api_key'),
            'client_secret' => config('shopify.api_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ])->throw();

        return $response->json();
    }

    public function generateState(): string
    {
        return Str::random(40);
    }
}
