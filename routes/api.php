<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DriverController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\RideComparisonController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\API\DriverEarningsController;
use App\Http\Controllers\API\DriverTruvController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function (): void {
    Route::post('/auth/verify', [AuthController::class, 'verify']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/update-role', [AuthController::class, 'updateRole']);

        Route::get('/user/profile', [UserController::class, 'profile']);
        Route::post('/user/profile/update', [UserController::class, 'updateProfile']);

        Route::post('/rides/compare', [RideComparisonController::class, 'compare']);

        Route::post('/driver/sync', [DriverController::class, 'sync']);
        Route::get('/driver/dashboard', [DriverController::class, 'dashboard']);
        Route::get('/driver/earnings', [DriverController::class, 'earnings']);


        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/read', [NotificationController::class, 'markRead']);
    });
});


Route::middleware(['throttle:api', 'auth:sanctum'])->prefix('driver/truv')->group(function (): void {
    Route::get('/search-company', [DriverTruvController::class, 'searchCompany']);
    Route::post('/create-token', [DriverTruvController::class, 'createToken']);
    Route::post('/exchange-token', [DriverTruvController::class, 'exchangeToken']);
    Route::get('/report', [DriverTruvController::class, 'report']);
    Route::get('/status', [DriverTruvController::class, 'status']);
});

Route::middleware(['throttle:api', 'auth:sanctum'])->group(function (): void {
    Route::get('/driver/earnings/summary', [DriverEarningsController::class, 'summary']);
    Route::get('/driver/earnings/platforms', [DriverEarningsController::class, 'platforms']);
    Route::post('/driver/earnings/refresh', [DriverEarningsController::class, 'refresh']);
});
