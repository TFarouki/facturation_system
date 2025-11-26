<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\CycleController;
use App\Http\Controllers\Api\SalesController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TaxController;
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
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('units', UnitController::class);
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('purchases', PurchaseController::class);
    Route::apiResource('taxes', TaxController::class);
    
    // Additional Routes
    Route::put('/products/{product}/prices', [ProductController::class, 'updatePrices']);
    Route::post('/purchases/{purchase}/attach-file', [PurchaseController::class, 'attachFile']);
    
    // Cycles
    Route::get('/cycles', [CycleController::class, 'index']);
    Route::post('/cycles', [CycleController::class, 'store']);
    Route::get('/cycles/{cycle}', [CycleController::class, 'show']);
    Route::post('/cycles/{cycle}/movements', [CycleController::class, 'addMovement']);
    Route::post('/cycles/{cycle}/close', [CycleController::class, 'close']);
    
    // Sales
    Route::get('/sales', [SalesController::class, 'index']);
    Route::post('/sales', [SalesController::class, 'store']);
    Route::get('/sales/{receipt}', [SalesController::class, 'show']);
    
    // Reports
    Route::get('/reports/profit', [ReportController::class, 'profit']);
    Route::post('/reports/monthly-zero', [ReportController::class, 'monthlyZero']);

    // Settings
    Route::get('/settings', [SettingController::class, 'index']);
    Route::put('/settings', [SettingController::class, 'update']);
    Route::post('/settings/logo', [SettingController::class, 'uploadLogo']);
});
