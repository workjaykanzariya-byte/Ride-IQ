<?php

namespace App\Services;

use App\Jobs\SyncLinkedAccountJob;
use App\Models\DriverEarning;
use App\Models\DriverTrip;
use App\Models\LinkedAccount;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DriverSyncService
{
    public function dispatchSyncForUser(User $user): void
    {
        $user->linkedAccounts()
            ->where('is_connected', true)
            ->whereIn('provider', ['uber', 'lyft'])
            ->get()
            ->each(fn (LinkedAccount $account) => SyncLinkedAccountJob::dispatch($account->id));
    }

    public function syncLinkedAccount(LinkedAccount $account): int
    {
        $providerService = match ($account->provider) {
            'uber' => app(UberService::class),
            'lyft' => app(LyftService::class),
            default => null,
        };

        if (! $providerService instanceof ProviderServiceInterface) {
            return 0;
        }

        $trips = $providerService->fetchDriverTrips($account->access_token);

        $synced = 0;

        DB::transaction(function () use ($account, $trips, &$synced): void {
            foreach ($trips as $trip) {
                DriverTrip::query()->updateOrCreate([
                    'provider' => $account->provider,
                    'trip_id_external' => (string) ($trip['trip_id_external'] ?? ''),
                ], [
                    'user_id' => $account->user_id,
                    'earnings' => (float) ($trip['earnings'] ?? 0),
                    'distance' => isset($trip['distance']) ? (float) $trip['distance'] : null,
                    'duration' => isset($trip['duration']) ? (int) $trip['duration'] : null,
                    'trip_date' => Carbon::parse($trip['trip_date'] ?? now())->toDateString(),
                    'meta' => $trip,
                ]);

                $synced++;
            }

            $account->update(['last_synced_at' => now()]);
        });

        $this->aggregateDailyEarnings($account->user_id);

        return $synced;
    }

    public function aggregateDailyEarnings(int $userId): void
    {
        $dailyRows = DriverTrip::query()
            ->where('user_id', $userId)
            ->selectRaw('trip_date as date, SUM(earnings) as total_earnings, COUNT(*) as total_trips, SUM(duration) as total_duration')
            ->groupBy('trip_date')
            ->get();

        foreach ($dailyRows as $row) {
            DriverEarning::query()->updateOrCreate([
                'user_id' => $userId,
                'date' => $row->date,
            ], [
                'total_earnings' => (float) $row->total_earnings,
                'total_trips' => (int) $row->total_trips,
                'total_hours' => round(((int) $row->total_duration) / 3600, 2),
            ]);
        }
    }
}
