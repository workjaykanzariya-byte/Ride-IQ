<?php

namespace App\Services;

class AggregatorService
{
    public function __construct(
        private readonly LocationServiceInterface $locationService,
        private readonly UberService $uberService,
        private readonly UberMockService $uberMockService
    ) {}

    /**
     * @return array{provider: string, rides: array<int, array{type: string, price: string, eta: string}>}|array{}
     */
    public function getRideEstimates(string $pickup, string $drop): array
    {
        $pickupCoordinates = $this->locationService->getCoordinates($pickup);
        $dropCoordinates = $this->locationService->getCoordinates($drop);

        $startLat = (float) data_get($pickupCoordinates, 'lat');
        $startLng = (float) data_get($pickupCoordinates, 'lng');
        $endLat = (float) data_get($dropCoordinates, 'lat');
        $endLng = (float) data_get($dropCoordinates, 'lng');

        if (config('services.uber.enabled')) {
            return $this->uberService->getEstimates($startLat, $startLng, $endLat, $endLng);
        }

        return $this->uberMockService->getEstimates($startLat, $startLng, $endLat, $endLng);
    }
}
