<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DriverController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\RideComparisonController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\API\TruvController;
use App\Http\Controllers\API\TruvWebhookController;
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


Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
    Route::post('/truv/create-user', [TruvController::class, 'createUser']);
    Route::post('/truv/bridge-token', [TruvController::class, 'bridgeToken']);
    Route::post('/truv/exchange-token', [TruvController::class, 'exchangeToken']);
    Route::get('/truv/profile', [TruvController::class, 'profile']);
    Route::get('/truv/income', [TruvController::class, 'income']);
    Route::post('/truv/refresh', [TruvController::class, 'refresh']);
    Route::delete('/truv/disconnect', [TruvController::class, 'disconnect']);
});

Route::post('/webhooks/truv', [TruvWebhookController::class, 'handle'])->middleware('throttle:api');
