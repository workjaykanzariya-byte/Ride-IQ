<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

class AyroService extends BaseProviderService
{
    protected function providerName(): string
    {
        return 'ayro';
    }

    public function getRideOptions(float $pickupLat, float $pickupLng, float $dropLat, float $dropLng): array
    {
        try {
            $response = $this->request('GET', '/api/v1/options', [
                'start_lat' => $pickupLat,
                'start_lng' => $pickupLng,
                'end_lat' => $dropLat,
                'end_lng' => $dropLng,
            ]);

            return is_array($response['options'] ?? null) ? $response['options'] : [];
        } catch (Throwable $exception) {
            Log::warning('Ayro ride options request failed', ['error' => $exception->getMessage()]);

            return [];
        }
    }

    public function fetchDriverTrips(string $accessToken): array
    {
        return [];
    }
}
