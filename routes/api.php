<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\UUserController;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Unified login route (for both SuperAdmin and UUsers)
Route::post('/login', [AuthController::class, 'login']);

// SuperAdmin-specific auth and management routes
Route::prefix('superadmin')->group(function () {
    Route::post('login', [SuperAdminController::class, 'login']);

    Route::middleware(['auth:sanctum', 'role:superadmin'])->group(function () {
        Route::post('logout', [SuperAdminController::class, 'logout']);
        Route::get('profile', [SuperAdminController::class, 'profile']);
        Route::put('profile', [SuperAdminController::class, 'updateProfile']);
        Route::post('password/change', [SuperAdminController::class, 'changePassword']);

        Route::apiResource('admins', SuperAdminController::class)->only(['index', 'store']);
        Route::put('admins/{id}/status', [SuperAdminController::class, 'updateStatus']);
    });
});

// UUsers management routes clearly under superadmin protection
Route::middleware(['auth:sanctum', 'role:superadmin'])->group(function () {
    Route::apiResource('uusers', UUserController::class);
    Route::put('uusers/{id}/status', [UUserController::class, 'updateStatus']);
    Route::put('uusers/{id}/password', [UUserController::class, 'updatePassword']);

    // (Optional specific routes clearly if needed separately)
    Route::post('/uuser/add', [UUserController::class, 'store']);
    Route::get('/uuser/{id}', [UUserController::class, 'show']);
    Route::put('/uuser/{id}', [UUserController::class, 'update']);
    Route::delete('/uuser/{id}', [UUserController::class, 'destroy']);
});
