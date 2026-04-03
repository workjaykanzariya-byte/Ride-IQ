<?php

namespace App\Services;

interface ProviderServiceInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRideOptions(float $pickupLat, float $pickupLng, float $dropLat, float $dropLng): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchDriverTrips(string $accessToken): array;
}
