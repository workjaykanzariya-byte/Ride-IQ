<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleLocationService implements LocationServiceInterface
{
    public function getCoordinates(string $place): array
    {
        $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $place,
            'key' => config('services.google.key'),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google Geocoding API request failed.');
        }

        $location = data_get($response->json(), 'results.0.geometry.location');

        if (! is_array($location) || ! isset($location['lat'], $location['lng'])) {
            throw new RuntimeException('Unable to resolve coordinates from Google Geocoding API.');
        }

        return [
            'lat' => (float) $location['lat'],
            'lng' => (float) $location['lng'],
        ];
    }
}
