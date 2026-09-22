<?php

use App\Http\Controllers\Auth\ShopifyAuthController;
use App\Http\Controllers\EmbeddedAppController;
use App\Http\Controllers\PixelEventController;
use App\Http\Controllers\PostPurchaseController;
use App\Http\Controllers\ProxyController;
use App\Http\Controllers\ThankYouController;
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

// Shopify App Proxy: storefront theme blocks call these at
// https://{shop}/apps/vantora/* (signature-verified, see shopify.app.toml
// [app_proxy] and VerifyShopifyAppProxySignature).
Route::prefix('apps/vantora')->middleware('shopify.proxy')->group(function () {
    Route::get('/recommendations', [ProxyController::class, 'recommendations']);
    Route::get('/fbt', [ProxyController::class, 'frequentlyBoughtTogether']);
    Route::get('/free-gifts', [ProxyController::class, 'freeGifts']);
});

// Post-purchase extension backend (extensions/post-purchase-upsell). Public
// like /pixel/events -- the post-purchase sandbox can't attach a session
// token either. UNTESTED, see PostPurchaseController docblock.
Route::post('/post-purchase/best-offer', [PostPurchaseController::class, 'bestOffer'])
    ->middleware('throttle:60,1');

// Thank You page extension backend (extensions/thank-you-blocks).
// Authenticated via the session token the extension attaches through
// shopify.sessionToken.get() -- unlike post-purchase/best-offer, this
// surface does expose that API. See VerifyShopifyExtensionSessionToken.
Route::prefix('thank-you')->middleware(['throttle:60,1', 'shopify.extension_session'])->group(function () {
    Route::post('/data', [ThankYouController::class, 'data']);
    Route::post('/survey', [ThankYouController::class, 'survey']);
});

// Web Pixel ingestion (extensions/vantora-pixel). Public and unauthenticated
// -- the pixel sandbox can't attach a session token or app-proxy signature
// -- so it's rate-limited instead.
Route::post('/pixel/events', [PixelEventController::class, 'store'])
    ->middleware('throttle:120,1');

// Embedded admin app entry point: resources/views/app.blade.php, the
// Polaris + App Bridge SPA built by Laravel's own Vite pipeline.
Route::get('/', [EmbeddedAppController::class, 'show'])->name('embedded.entry');
