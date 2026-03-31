<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
        $validated = $request->validate([
            'pickup' => ['required', 'string'],
            'drop' => ['required', 'string'],
        ]);

        $estimates = $this->aggregatorService->getRideEstimates(
            $validated['pickup'],
            $validated['drop']
        );

        return response()->json($estimates);
    }
}
