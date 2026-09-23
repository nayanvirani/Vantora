<?php

namespace App\Services\Shopify;

use App\Models\Shop;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the Admin GraphQL API for one shop's access token.
 * Every other Shopify-calling service builds on this rather than calling
 * Http::* directly, so the auth header and endpoint shape live in one place.
 */
class ShopifyApiClient
{
    public function __construct(private readonly Shop $shop) {}

    /**
     * @param  array<string, mixed>  $variables
     */
    public function graphql(string $query, array $variables = []): Response
    {
        $version = config('shopify.api_version');

        return Http::withHeaders([
            'X-Shopify-Access-Token' => $this->shop->access_token,
            'Content-Type' => 'application/json',
        ])
            ->baseUrl("https://{$this->shop->domain}/admin/api/{$version}")
            ->post('/graphql.json', [
                'query' => $query,
                'variables' => $variables,
            ])
            ->throw();
    }
}
