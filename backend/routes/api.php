<?php

use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FeatureConfigController;
use App\Http\Controllers\Api\RecipeController;
use App\Http\Controllers\Api\ShopController;
use Illuminate\Support\Facades\Route;

Route::middleware('shopify.session')->group(function () {
    Route::get('/shop', [ShopController::class, 'show']);

    Route::post('/billing/subscribe', [BillingController::class, 'subscribe']);

    Route::get('/dashboard', [DashboardController::class, 'show']);

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
});
