<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies Shopify's app proxy signature on storefront -> app requests
 * (theme blocks calling /apps/vantora/*). Signing scheme is distinct from
 * webhook HMAC: query params (minus `signature`) are sorted, joined as
 * `key=value` with no separator, then HMAC-SHA256'd with the API secret.
 * See https://shopify.dev/docs/apps/build/online-store/display-dynamic-data#calculate-a-digital-signature
 */
class VerifyShopifyAppProxySignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $params = $request->query();
        $signature = $params['signature'] ?? null;

        abort_if(! $signature, 401, 'Missing app proxy signature.');

        unset($params['signature']);
        ksort($params);

        $message = '';
        foreach ($params as $key => $value) {
            $value = is_array($value) ? implode(',', $value) : $value;
            $message .= "{$key}={$value}";
        }

        $computed = hash_hmac('sha256', $message, config('shopify.api_secret'));

        abort_unless(hash_equals($computed, (string) $signature), 401, 'Invalid app proxy signature.');

        $shopDomain = $request->query('shop');
        $shop = Shop::query()->where('domain', $shopDomain)->whereNull('uninstalled_at')->first();

        abort_if(! $shop, 404, 'Unknown shop.');

        $request->attributes->set('shop', $shop);

        return $next($request);
    }
}
