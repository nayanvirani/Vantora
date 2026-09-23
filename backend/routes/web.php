<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ShopController as AdminShopController;
use App\Http\Controllers\Auth\ShopifyAuthController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Shopify OAuth install flow.
Route::get('/auth', [ShopifyAuthController::class, 'install'])->name('shopify.auth.install');
Route::get('/auth/callback', [ShopifyAuthController::class, 'callback'])->name('shopify.auth.callback');

// Shopify webhooks -- HMAC-verified (see VerifyShopifyWebhookSignature), no
// CSRF, no session auth. Topics match shopify.app.toml's declared
// subscriptions exactly.
Route::prefix('webhooks/shopify')->middleware('shopify.webhook')->group(function () {
    Route::post('/app-uninstalled', [WebhookController::class, 'appUninstalled']);
    Route::post('/app-subscriptions-update', [WebhookController::class, 'appSubscriptionsUpdate']);
    Route::post('/shop-update', [WebhookController::class, 'shopUpdate']);
    Route::post('/themes-publish', [WebhookController::class, 'themesPublish']);
    Route::post('/themes-update', [WebhookController::class, 'themesUpdate']);
    Route::post('/products-update', [WebhookController::class, 'productsUpdate']);
    Route::post('/products-delete', [WebhookController::class, 'productsDelete']);
    Route::post('/orders-create', [WebhookController::class, 'ordersCreate']);

    // GDPR mandatory compliance webhooks.
    Route::post('/customers-data-request', [WebhookController::class, 'customersDataRequest']);
    Route::post('/customers-redact', [WebhookController::class, 'customersRedact']);
    Route::post('/shop-redact', [WebhookController::class, 'shopRedact']);
});

// Super Admin -- entirely separate from Shopify OAuth (see config/auth.php's
// 'admin' guard). Not an embedded Shopify surface, so this is plain
// server-rendered Blade, not the Polaris/App Bridge SPA.
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', function () {
        return auth('admin')->check()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('admin.login');
    })->name('home');

    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login'])->name('login.attempt');
    });

    Route::middleware('auth:admin')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/shops', [AdminShopController::class, 'index'])->name('shops.index');
        Route::get('/shops/{shop}', [AdminShopController::class, 'show'])->name('shops.show');
        Route::post('/shops/{shop}/pause', [AdminShopController::class, 'pause'])->name('shops.pause');
        Route::post('/shops/{shop}/unpause', [AdminShopController::class, 'unpause'])->name('shops.unpause');
        Route::post('/shops/{shop}/plan-override', [AdminShopController::class, 'overridePlan'])->name('shops.plan-override');
    });
});
