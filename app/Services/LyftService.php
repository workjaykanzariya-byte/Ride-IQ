<?php

namespace App\Services;

class LyftService extends BaseProviderService
{
    protected function providerName(): string
    {
        return 'lyft';
    }

    public function getRideOptions(float $pickupLat, float $pickupLng, float $dropLat, float $dropLng): array
    {
        $response = $this->request('POST', '/v1/rides/quote', [
            'origin' => ['lat' => $pickupLat, 'lng' => $pickupLng],
            'destination' => ['lat' => $dropLat, 'lng' => $dropLng],
        ]);

        return $response['options'] ?? [];
    }

    public function fetchDriverTrips(string $accessToken): array
    {
        $response = $this->request('GET', '/v1/driver/history', [], $accessToken);

        return $response['trips'] ?? [];
    }
}
