<?php

use App\Http\Controllers\Auth\ShopifyAuthController;
use App\Http\Controllers\EmbeddedAppController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Shopify OAuth
Route::get('/auth', [ShopifyAuthController::class, 'install'])->name('shopify.auth.install');
Route::get('/auth/callback', [ShopifyAuthController::class, 'callback'])->name('shopify.auth.callback');

// Shopify webhooks (HMAC-verified inside the controller, no CSRF, no session auth)
Route::prefix('webhooks/shopify')->group(function () {
    Route::post('/app-uninstalled', [WebhookController::class, 'appUninstalled']);
    Route::post('/app-subscriptions-update', [WebhookController::class, 'appSubscriptionsUpdate']);
    Route::post('/shop-update', [WebhookController::class, 'shopUpdate']);
    Route::post('/themes-publish', [WebhookController::class, 'themesPublish']);
    Route::post('/themes-update', [WebhookController::class, 'themesUpdate']);
    Route::post('/products-update', [WebhookController::class, 'productsUpdate']);
    Route::post('/products-delete', [WebhookController::class, 'productsDelete']);
    Route::post('/orders-create', [WebhookController::class, 'ordersCreate']);

    // GDPR mandatory compliance webhooks
    Route::post('/customers-data-request', [WebhookController::class, 'customersDataRequest']);
    Route::post('/customers-redact', [WebhookController::class, 'customersRedact']);
    Route::post('/shop-redact', [WebhookController::class, 'shopRedact']);
});

// Embedded admin app entry point. The actual UI is a static SPA build
// (Polaris + App Bridge) served from public/app/, this just ensures the
// shop/host query params survive straight to index.html.
Route::get('/', [EmbeddedAppController::class, 'show'])->name('embedded.entry');
