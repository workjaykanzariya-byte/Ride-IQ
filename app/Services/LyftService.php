<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

class LyftService extends BaseProviderService
{
    protected function providerName(): string
    {
        return 'lyft';
    }

    public function getRideOptions(float $pickupLat, float $pickupLng, float $dropLat, float $dropLng): array
    {
        try {
            $response = $this->request('POST', '/v1/rides/quote', [
                'origin' => ['lat' => $pickupLat, 'lng' => $pickupLng],
                'destination' => ['lat' => $dropLat, 'lng' => $dropLng],
            ]);

            return is_array($response['options'] ?? null) ? $response['options'] : [];
        } catch (Throwable $exception) {
            Log::warning('Lyft ride options request failed', ['error' => $exception->getMessage()]);

            return [];
        }
    }

    public function fetchDriverTrips(string $accessToken): array
    {
        try {
            $response = $this->request('GET', '/v1/driver/history', [], $accessToken);

            return is_array($response['trips'] ?? null) ? $response['trips'] : [];
        } catch (Throwable $exception) {
            Log::warning('Lyft driver trips request failed', ['error' => $exception->getMessage()]);

            return [];
        }
    }
}
