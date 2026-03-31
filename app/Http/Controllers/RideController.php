<?php

namespace App\Http\Controllers;

use App\Services\AggregatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RideController extends Controller
{
    public function getEstimates(Request $request): JsonResponse
    {
        $request->validate([
            'pickup' => 'required|string',
            'drop'   => 'required|string',
        ]);

        $service = app(AggregatorService::class);

        return response()->json(
            $service->getRideEstimates($request->pickup, $request->drop)
        );
    }
}
