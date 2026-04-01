<?php

namespace App\Http\Controllers;

use App\Services\AggregatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RideController extends Controller
{
    public function __construct(private readonly AggregatorService $aggregatorService)
    {
    }

    public function getEstimates(Request $request): JsonResponse
    {
        $request->validate([
            'pickup' => 'required|string',
            'drop' => 'required|string',
        ]);

        return response()->json(
            $this->aggregatorService->getRideEstimates($request->pickup, $request->drop)
        );
    }
}
