<?php

namespace App\Services\Shopify;

use Illuminate\Http\Request;

class ShopifyWebhookVerifier
{
    public function verify(Request $request): bool
    {
        $hmacHeader = $request->header('X-Shopify-Hmac-Sha256');

        if (! $hmacHeader) {
            return false;
        }

        $computed = base64_encode(
            hash_hmac('sha256', $request->getContent(), config('shopify.api_secret'), true)
        );

        return hash_equals($computed, $hmacHeader);
    }
}
