<?php

namespace App\Services;

class AggregatorService
{
    public function getRideEstimates(string $pickup, string $drop): array
    {
        $locationService = app(LocationServiceInterface::class);

        $pickupCoords = $locationService->getCoordinates($pickup);
        $dropCoords   = $locationService->getCoordinates($drop);

        $uberService = config('services.uber.enabled')
            ? app(UberService::class)
            : app(UberMockService::class);

        return $uberService->getEstimates(
            $pickupCoords['lat'],
            $pickupCoords['lng'],
            $dropCoords['lat'],
            $dropCoords['lng']
        );
    }
}
