<?php

use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\ShopController;
use Illuminate\Support\Facades\Route;

// Embedded admin SPA API -- session-token verified (App Bridge), see
// VerifyShopifySessionToken. $shop is resolved by the middleware and
// available via $request->attributes->get('shop').
Route::middleware('shopify.session')->group(function () {
    Route::get('/shop', [ShopController::class, 'show']);
    Route::get('/billing/pricing-plans-url', [BillingController::class, 'pricingPlansUrl']);
    Route::get('/billing/plans', [BillingController::class, 'plans']);
});
