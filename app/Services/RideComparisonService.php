<?php

namespace App\Services;

use App\Models\Ride;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RideComparisonService
{
    public function __construct(
        private readonly UberService $uberService,
        private readonly LyftService $lyftService,
        private readonly AyroService $ayroService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function compareAndStore(
        int $userId,
        float $pickupLat,
        float $pickupLng,
        float $dropLat,
        float $dropLng
    ): array {
        $providers = [
            'uber' => $this->uberService,
            'lyft' => $this->lyftService,
            'ayro' => $this->ayroService,
        ];

        $normalizedOptions = [];

        foreach ($providers as $provider => $service) {
            $options = $service->getRideOptions($pickupLat, $pickupLng, $dropLat, $dropLng);

            foreach ($options as $option) {
                $normalizedOptions[] = [
                    'provider' => $provider,
                    'service_type' => (string) Arr::get($option, 'service_type', 'standard'),
                    'price' => (float) Arr::get($option, 'price', 0),
                    'eta' => (int) Arr::get($option, 'eta', 0),
                    'duration' => (int) Arr::get($option, 'duration', 0),
                    'meta' => $option,
                ];
            }
        }

        $ride = DB::transaction(function () use ($userId, $pickupLat, $pickupLng, $dropLat, $dropLng, $normalizedOptions): Ride {
            $ride = Ride::query()->create([
                'user_id' => $userId,
                'pickup_lat' => $pickupLat,
                'pickup_lng' => $pickupLng,
                'drop_lat' => $dropLat,
                'drop_lng' => $dropLng,
                'status' => 'quoted',
                'meta' => ['options_count' => count($normalizedOptions)],
            ]);

            $ride->rideOptions()->createMany($normalizedOptions);

            return $ride->load('rideOptions');
        });

        return [
            'ride_id' => $ride->id,
            'options' => $ride->rideOptions,
        ];
    }
}
