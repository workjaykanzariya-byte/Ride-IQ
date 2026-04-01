<?php

use App\Http\Controllers\Api\FirebaseLoginController;
use App\Http\Controllers\RideController;
use App\Services\LocationServiceInterface;
use Illuminate\Support\Facades\Route;

Route::post('/firebase-login', FirebaseLoginController::class)
    ->middleware(['guest', 'throttle:15,1']);

Route::post('/ride-estimates', [RideController::class, 'getEstimates']);

Route::get('/test-location', function () {
    $service = app(LocationServiceInterface::class);

    return $service->getCoordinates('Ahmedabad Airport');
});
