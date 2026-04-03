<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DriverController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\RideComparisonController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function (): void {
    Route::post('/auth/verify', [AuthController::class, 'verify']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/update-role', [AuthController::class, 'updateRole']);

        Route::post('/rides/compare', [RideComparisonController::class, 'compare']);

        Route::post('/driver/sync', [DriverController::class, 'sync']);
        Route::get('/driver/dashboard', [DriverController::class, 'dashboard']);
        Route::get('/driver/earnings', [DriverController::class, 'earnings']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/read', [NotificationController::class, 'markRead']);
    });
});
