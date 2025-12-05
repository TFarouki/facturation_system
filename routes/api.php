<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\DistributorController;
use App\Http\Controllers\Api\DistributionOrderController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\CycleController;
use App\Http\Controllers\Api\SalesController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TaxController;
use App\Http\Controllers\Api\StockReviewController;
use App\Http\Controllers\Api\ProductFamilyController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/change-password', [UserController::class, 'changePassword']);

    // Resource Routes
    Route::apiResource('users', UserController::class);
    Route::apiResource('products', ProductController::class);
    Route::get('/product-families/search', [ProductFamilyController::class, 'search']);
    Route::apiResource('product-families', ProductFamilyController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('units', UnitController::class);
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('distributors', DistributorController::class);
    Route::get('/distributors/{distributor}/committed-products', [DistributionOrderController::class, 'getCommittedProductsByDistributor']);
    Route::get('/distribution-orders/next-number', [DistributionOrderController::class, 'getNextOrderNumber']);
    Route::get('/distribution-orders/committed-products', [DistributionOrderController::class, 'getCommittedProducts']);
    Route::apiResource('distribution-orders', DistributionOrderController::class);
    Route::get('/distribution-orders/report/sales', [DistributionOrderController::class, 'salesReport']);
    Route::apiResource('purchases', PurchaseController::class);
    Route::apiResource('taxes', TaxController::class);
    
    // Additional Routes
    Route::put('/products/{product}/prices', [ProductController::class, 'updatePrices']);
    Route::get('/products/{product}/stock-history', [ProductController::class, 'stockHistory']);
    Route::get('/products/{product}/purchase-price-history', [ProductController::class, 'purchasePriceHistory']);
    Route::post('/purchases/{purchase}/attach-file', [PurchaseController::class, 'attachFile']);
    
    // Cycles
    Route::get('/cycles', [CycleController::class, 'index']);
    Route::post('/cycles', [CycleController::class, 'store']);
    Route::get('/cycles/{cycle}', [CycleController::class, 'show']);
    Route::post('/cycles/{cycle}/movements', [CycleController::class, 'addMovement']);
    Route::post('/cycles/{cycle}/close', [CycleController::class, 'close']);
    
    // Clients
    Route::get('/clients/search', [ClientController::class, 'search']);
    Route::apiResource('clients', ClientController::class);

    // Sales
    Route::get('/sales', [SalesController::class, 'index']);
    Route::get('/sales/next-receipt-number', [SalesController::class, 'getNextReceiptNumber']);
    Route::post('/sales', [SalesController::class, 'store']);
    Route::get('/sales/{receipt}', [SalesController::class, 'show']);
    Route::put('/sales/{receipt}', [SalesController::class, 'update']);
    Route::delete('/sales/{receipt}', [SalesController::class, 'destroy']);
    
    // Reports
    Route::get('/reports/profit', [ReportController::class, 'profit']);
    Route::post('/reports/monthly-zero', [ReportController::class, 'monthlyZero']);

    // Settings
    Route::get('/settings', [SettingController::class, 'index']);
    Route::put('/settings', [SettingController::class, 'update']);
    Route::post('/settings/logo', [SettingController::class, 'uploadLogo']);

    // Stock Reviews
    Route::get('/stock-reviews', [StockReviewController::class, 'index']);
    Route::post('/stock-reviews', [StockReviewController::class, 'store']);
    Route::get('/stock-reviews/pending/{distributor}', [StockReviewController::class, 'getPending']);
    Route::get('/stock-reviews/{stockReview}', [StockReviewController::class, 'show']);
    Route::post('/stock-reviews/{stockReview}/confirm', [StockReviewController::class, 'confirm']);
    Route::post('/stock-reviews/{stockReview}/cancel', [StockReviewController::class, 'cancel']);
    Route::delete('/stock-reviews/{stockReview}', [StockReviewController::class, 'destroy']);
});
