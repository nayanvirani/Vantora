<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the session token App Bridge attaches as a Bearer token on every
 * embedded-app API request, per Shopify's session token auth model.
 */
class VerifyShopifySessionToken
{
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

        $shop = Shop::query()->where('domain', $shopDomain)->whereNull('uninstalled_at')->first();

        abort_if(! $shop, 401, 'Unknown or uninstalled shop.');

        $request->attributes->set('shop', $shop);

        return $next($request);
    }
}
