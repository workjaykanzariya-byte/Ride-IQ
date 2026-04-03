<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DriverEarning;
use App\Models\DriverTrip;
use App\Services\DriverSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly DriverSyncService $driverSyncService)
    {
    }

    public function sync(Request $request): JsonResponse
    {
        $this->driverSyncService->dispatchSyncForUser($request->user());

        return $this->success('Driver sync queued');
    }

    public function dashboard(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $summary = DriverEarning::query()
            ->where('user_id', $userId)
            ->selectRaw('SUM(total_earnings) as earnings, SUM(total_trips) as trips, SUM(total_hours) as hours')
            ->first();

        $latestTrips = DriverTrip::query()
            ->where('user_id', $userId)
            ->latest('trip_date')
            ->limit(10)
            ->get();

        return $this->success('Driver dashboard', [
            'totals' => [
                'earnings' => (float) ($summary?->earnings ?? 0),
                'trips' => (int) ($summary?->trips ?? 0),
                'hours' => (float) ($summary?->hours ?? 0),
            ],
            'recent_trips' => $latestTrips,
        ]);
    }

    public function earnings(Request $request): JsonResponse
    {
        $earnings = DriverEarning::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('date')
            ->paginate(30);

        return $this->success('Driver earnings', [
            'earnings' => $earnings,
        ]);
    }
}
