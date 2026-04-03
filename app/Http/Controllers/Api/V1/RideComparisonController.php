<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RideComparisonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RideComparisonController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly RideComparisonService $rideComparisonService)
    {
    }

    public function compare(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pickup_lat' => ['required', 'numeric', 'between:-90,90'],
            'pickup_lng' => ['required', 'numeric', 'between:-180,180'],
            'drop_lat' => ['required', 'numeric', 'between:-90,90'],
            'drop_lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        try {
            $result = $this->rideComparisonService->compareAndStore(
                $request->user()->id,
                (float) $validated['pickup_lat'],
                (float) $validated['pickup_lng'],
                (float) $validated['drop_lat'],
                (float) $validated['drop_lng'],
            );
        } catch (RuntimeException $exception) {
            return $this->error('Unable to fetch ride options', ['error' => $exception->getMessage()], 503);
        }

        return $this->success('Ride options fetched', $result);
    }
}
