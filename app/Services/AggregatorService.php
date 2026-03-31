<?php

namespace App\Services;

class AggregatorService
{
    public function __construct(private readonly LocationServiceInterface $locationService)
    {
    }

    public function getRideEstimates(string $pickup, string $drop): array
    {
        $pickupCoords = $this->locationService->getCoordinates($pickup);
        $dropCoords = $this->locationService->getCoordinates($drop);

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
