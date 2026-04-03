<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

class UberService extends BaseProviderService
{
    protected function providerName(): string
    {
        return 'uber';
    }

    public function getRideOptions(float $pickupLat, float $pickupLng, float $dropLat, float $dropLng): array
    {
        try {
            $response = $this->request('GET', '/v1/estimates', [
                'pickup_lat' => $pickupLat,
                'pickup_lng' => $pickupLng,
                'drop_lat' => $dropLat,
                'drop_lng' => $dropLng,
            ]);

            return is_array($response['options'] ?? null) ? $response['options'] : [];
        } catch (Throwable $exception) {
            Log::warning('Uber ride options request failed', ['error' => $exception->getMessage()]);

            return [];
        }
    }

    public function fetchDriverTrips(string $accessToken): array
    {
        try {
            $response = $this->request('GET', '/v1/driver/trips', [], $accessToken);

            return is_array($response['trips'] ?? null) ? $response['trips'] : [];
        } catch (Throwable $exception) {
            Log::warning('Uber driver trips request failed', ['error' => $exception->getMessage()]);

            return [];
        }
    }
}
