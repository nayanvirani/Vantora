<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the session token Shopify App Bridge attaches to every
 * embedded-admin API call (Authorization: Bearer <token>). JWT signed
 * HS256 with the app's client secret; `dest` identifies the shop, `aud`
 * must equal this app's client_id (distinguishing an embedded-admin-
 * issued token from any other Shopify-issued JWT).
 */
class VerifyShopifySessionToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        abort_if(! $token, 401, 'Missing session token.');

        try {
            $claims = JWT::decode($token, new Key(config('shopify.api_secret'), 'HS256'));
        } catch (\Throwable) {
            abort(401, 'Invalid session token.');
        }

        abort_unless(
            ($claims->aud ?? null) === config('shopify.api_key'),
            401,
            'Token was not issued for this app.'
        );

        $shopDomain = parse_url($claims->dest ?? '', PHP_URL_HOST);

        $shop = Shop::where('domain', $shopDomain)->whereNull('uninstalled_at')->first();

        abort_if(! $shop, 401, 'Unknown or uninstalled shop.');
        abort_if($shop->isAdminPaused(), 403, 'This shop has been paused.');

        $request->attributes->set('shop', $shop);

        return $next($request);
    }
}
