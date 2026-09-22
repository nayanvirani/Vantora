<?php

use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\VerifyShopifyAppProxySignature;
use App\Http\Middleware\VerifyShopifyExtensionSessionToken;
use App\Http\Middleware\VerifyShopifySessionToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
            'apps/*',
            'pixel/*',
            'post-purchase/*',
            'thank-you/*',
            'checkout/*',
        ]);

        $middleware->alias([
            'shopify.session' => VerifyShopifySessionToken::class,
            'shopify.proxy' => VerifyShopifyAppProxySignature::class,
            'shopify.extension_session' => VerifyShopifyExtensionSessionToken::class,
            'active_subscription' => EnsureActiveSubscription::class,
        ]);

        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'apps/*', 'pixel/*', 'webhooks/*', 'post-purchase/*', 'thank-you/*', 'checkout/*') || $request->expectsJson(),
        );
    })->create();
