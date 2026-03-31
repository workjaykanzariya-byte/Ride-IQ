<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\RideController;
use App\Services\LocationServiceInterface;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function (): void {
    Route::post('send-otp', [AuthController::class, 'sendOtp'])->middleware('throttle:5,1');
    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::post('/ride-estimates', [RideController::class, 'getEstimates']);

Route::get('/test-location', function () {
    $service = app(LocationServiceInterface::class);

    return $service->getCoordinates('Ahmedabad Airport');
});
