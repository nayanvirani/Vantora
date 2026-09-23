<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every Shopify webhook carries an X-Shopify-Hmac-Sha256 header: a base64
 * HMAC-SHA256 of the raw request body, keyed with the app's client secret.
 * Verifying it is the only way to know a POST to /webhooks/shopify/* is
 * actually from Shopify and not a forged request against a public URL.
 */
class VerifyShopifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $hmacHeader = $request->header('X-Shopify-Hmac-Sha256');

        abort_if(! $hmacHeader, 401, 'Missing HMAC signature.');

        $computed = base64_encode(hash_hmac(
            'sha256',
            $request->getContent(),
            (string) config('shopify.api_secret'),
            true
        ));

        abort_unless(hash_equals($computed, $hmacHeader), 401, 'Invalid HMAC signature.');

        return $next($request);
    }
}
