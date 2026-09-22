<?php

use App\Http\Controllers\Api\AiOptimizerController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FeatureConfigController;
use App\Http\Controllers\Api\ProductLookupController;
use App\Http\Controllers\Api\RecipeController;
use App\Http\Controllers\Api\ShopController;
use Illuminate\Support\Facades\Route;

Route::middleware('shopify.session')->group(function () {
    // No free plan -- a shop that has never subscribed can only reach
    // billing itself, plus this one identity check the frontend uses to
    // decide whether to show the paywall before hitting anything else.
    Route::get('/shop', [ShopController::class, 'show']);
    Route::get('/billing/status', [BillingController::class, 'status']);

    Route::middleware('active_subscription')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'show']);

        Route::get('/billing/usage', [BillingController::class, 'usage']);
        Route::get('/products/lookup', [ProductLookupController::class, 'index']);

        Route::get('/analytics', [AnalyticsController::class, 'index']);
        Route::get('/analytics/traffic', [AnalyticsController::class, 'traffic']);
        Route::get('/analytics/score-history', [AnalyticsController::class, 'scoreHistory']);

        Route::post('/audits', [AuditController::class, 'store']);
        Route::get('/audits/latest', [AuditController::class, 'latest']);
        Route::get('/audits/{audit}', [AuditController::class, 'show']);
        Route::post('/audits/{audit}/issues/{issue}/dismiss', [AuditController::class, 'dismissIssue']);
        Route::post('/audits/{audit}/issues/{issue}/snooze', [AuditController::class, 'snoozeIssue']);

        Route::get('/feature-configs', [FeatureConfigController::class, 'index']);
        Route::post('/feature-configs', [FeatureConfigController::class, 'store']);
        Route::get('/feature-configs/{featureConfig}', [FeatureConfigController::class, 'show']);
        Route::put('/feature-configs/{featureConfig}', [FeatureConfigController::class, 'update']);
        Route::post('/feature-configs/{featureConfig}/activate', [FeatureConfigController::class, 'activate']);
        Route::post('/feature-configs/{featureConfig}/deactivate', [FeatureConfigController::class, 'deactivate']);
        Route::delete('/feature-configs/{featureConfig}', [FeatureConfigController::class, 'destroy']);

        Route::get('/recipes', [RecipeController::class, 'index']);
        Route::post('/recipes/{key}/preview', [RecipeController::class, 'preview']);
        Route::post('/recipes/{key}/apply', [RecipeController::class, 'apply']);

        Route::get('/ai/usage', [AiOptimizerController::class, 'usage']);
        Route::get('/ai/jobs', [AiOptimizerController::class, 'index']);
        Route::post('/ai/jobs', [AiOptimizerController::class, 'store']);
        Route::get('/ai/jobs/{aiJob}', [AiOptimizerController::class, 'show']);
        Route::post('/ai/jobs/{aiJob}/approve', [AiOptimizerController::class, 'approve']);
        Route::post('/ai/jobs/{aiJob}/discard', [AiOptimizerController::class, 'discard']);
    });
});
