<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoogleLocationService implements LocationServiceInterface
{
    public function getCoordinates(string $place): array
    {
        $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $place,
            'key' => config('services.google.key'),
        ]);

        if (! $response->successful() || empty($response['results'])) {
            return ['lat' => 23.0225, 'lng' => 72.5714];
        }

        $location = $response['results'][0]['geometry']['location'];

        return [
            'lat' => $location['lat'],
            'lng' => $location['lng'],
        ];
    }
}
