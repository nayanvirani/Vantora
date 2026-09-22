<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * No free plan: a shop with no active Shopify Managed Pricing subscription
 * can only reach billing status (to see the paywall) and the shop-identity
 * check the frontend needs to render it -- everything else in
 * routes/api.php is wrapped in this. Runs after shopify.session, so
 * $request->attributes->get('shop') is already resolved.
 */
class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $shop = $request->attributes->get('shop');

        abort_unless($shop->hasActiveSubscription(), 402, 'An active subscription is required.');

        return $next($request);
    }
}
