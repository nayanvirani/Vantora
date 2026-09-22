<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the session token checkout/thank-you UI extensions attach via
 * shopify.sessionToken.get() (Authorization: Bearer <token>) -- same JWT
 * signing (HS256, api_secret) and `dest` claim as the embedded admin app's
 * token (see VerifyShopifySessionToken), which is why these extension
 * calls were left fully unauthenticated up to now: nothing here checks the
 * `aud` claim the admin-app middleware requires, deliberately, since it's
 * unconfirmed whether an extension-issued token carries the same
 * `aud` = api_key value an embedded-admin-issued one does. A wrong strict
 * check here fails silent and unauthenticated is strictly worse, so this
 * stays a separate, looser middleware rather than reusing
 * VerifyShopifySessionToken as-is.
 */
class VerifyShopifyExtensionSessionToken
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

        $shopDomain = parse_url($claims->dest ?? '', PHP_URL_HOST);

        $shop = Shop::query()->where('domain', $shopDomain)->whereNull('uninstalled_at')->first();

        abort_if(! $shop, 401, 'Unknown or uninstalled shop.');

        $request->attributes->set('shop', $shop);

        return $next($request);
    }
}
