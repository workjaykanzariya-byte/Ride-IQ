<?php

namespace App\Services;

class AyroService extends BaseProviderService
{
    protected function providerName(): string
    {
        return 'ayro';
    }

    public function getRideOptions(float $pickupLat, float $pickupLng, float $dropLat, float $dropLng): array
    {
        $response = $this->request('GET', '/api/v1/options', [
            'start_lat' => $pickupLat,
            'start_lng' => $pickupLng,
            'end_lat' => $dropLat,
            'end_lng' => $dropLng,
        ]);

        return $response['options'] ?? [];
    }

    public function fetchDriverTrips(string $accessToken): array
    {
        return [];
    }
}
