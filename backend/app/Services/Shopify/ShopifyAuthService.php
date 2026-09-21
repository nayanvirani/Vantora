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

    public function exchangeCodeForToken(string $shop, string $code): array
    {
        $response = Http::asJson()->post("https://{$shop}/admin/oauth/access_token", [
            'client_id' => config('shopify.api_key'),
            'client_secret' => config('shopify.api_secret'),
            'code' => $code,
        ])->throw();

        return $response->json();
    }

    public function generateState(): string
    {
        return Str::random(40);
    }
}
