<?php

namespace App\Services\Shopify;

use App\Models\Shop;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyApiClient
{
    public function __construct(protected Shop $shop, protected ?ShopifyAuthService $auth = null)
    {
        $this->auth ??= new ShopifyAuthService();
    }

    public function graphql(string $query, array $variables = []): Response
    {
        $this->ensureFreshToken();
        $version = config('shopify.api_version');

        return Http::withHeaders([
            'X-Shopify-Access-Token' => $this->shop->access_token,
            'Content-Type' => 'application/json',
        ])
            ->baseUrl("https://{$this->shop->domain}/admin/api/{$version}")
            ->post('/graphql.json', [
                'query' => $query,
                // json_encode([]) produces `[]`, which Shopify's GraphQL
                // endpoint rejects outright ("Invalid variables parameter")
                // -- it requires an object. Every call site that omits
                // variables (the majority) was silently failing on this,
                // caught by each caller's own try/catch and logged as a
                // generic sync failure, which is why so many different
                // features were failing to save at once. Casting an empty
                // array to an object makes it encode as `{}`; non-empty
                // associative arrays are unaffected.
                'variables' => $variables ?: (object) [],
            ])
            ->throw();
    }

    public function rest(string $method, string $path, array $payload = []): Response
    {
        $this->ensureFreshToken();
        $version = config('shopify.api_version');

        return Http::withHeaders([
            'X-Shopify-Access-Token' => $this->shop->access_token,
        ])
            ->baseUrl("https://{$this->shop->domain}/admin/api/{$version}")
            ->{strtolower($method)}($path, $payload)
            ->throw();
    }

    /**
     * Offline access tokens expire after 1 hour (see the
     * add_token_refresh_columns_to_shops_table migration for why); this
     * refreshes and persists a new token/refresh_token pair just before a
     * call would otherwise fail with an expired token, so every caller of
     * graphql()/rest() gets a working token without knowing about expiry.
     */
    protected function ensureFreshToken(): void
    {
        if (! $this->shop->needsTokenRefresh()) {
            return;
        }

        try {
            $tokenData = $this->auth->refreshAccessToken($this->shop->domain, $this->shop->refresh_token);

            $this->shop->update([
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'],
                'access_token_expires_at' => now()->addSeconds($tokenData['expires_in']),
                'refresh_token_expires_at' => now()->addSeconds($tokenData['refresh_token_expires_in']),
            ]);
        } catch (\Throwable $e) {
            // Leave the stale token in place and let the caller's own
            // ->throw() surface the resulting 401 -- refresh failing here
            // (e.g. the refresh token itself expired after 90 days of no
            // activity) shouldn't crash with a different, more confusing
            // error than what actually happened.
            Log::warning('Shopify access token refresh failed', [
                'shop' => $this->shop->domain,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
