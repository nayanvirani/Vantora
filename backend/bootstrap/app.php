<?php

use App\Http\Middleware\VerifyShopifySessionToken;
use App\Http\Middleware\VerifyShopifyWebhookSignature;
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
        // Shopify POSTs webhooks without a CSRF token or session -- HMAC
        // verification (VerifyShopifyWebhookSignature) is the real auth.
        // api/* is already stateless/CSRF-exempt by Laravel's default
        // routing setup.
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);

        $middleware->alias([
            'shopify.webhook' => VerifyShopifyWebhookSignature::class,
            'shopify.session' => VerifyShopifySessionToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'webhooks/*') || $request->expectsJson(),
        );
    })->create();
