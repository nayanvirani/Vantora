<?php

namespace App\Services\Shopify;

use App\Models\Shop;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ShopifyApiClient
{
    public function __construct(protected Shop $shop)
    {
    }

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

    public function rest(string $method, string $path, array $payload = []): Response
    {
        $version = config('shopify.api_version');

        return Http::withHeaders([
            'X-Shopify-Access-Token' => $this->shop->access_token,
        ])
            ->baseUrl("https://{$this->shop->domain}/admin/api/{$version}")
            ->{strtolower($method)}($path, $payload)
            ->throw();
    }
}
