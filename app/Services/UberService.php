<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class UberService
{
    public function getEstimates(float $startLat, float $startLng, float $endLat, float $endLng): array
    {
        try {
            $price = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.uber.token'),
                'Accept'        => 'application/json',
            ])->get('https://api.uber.com/v1.2/estimates/price', [
                'start_latitude'  => $startLat,
                'start_longitude' => $startLng,
                'end_latitude'    => $endLat,
                'end_longitude'   => $endLng,
            ]);

            if (!$price->successful()) {
                return [];
            }

            return [
                'provider' => 'uber',
                'rides'    => collect($price['prices'])->map(function ($ride) {
                    return [
                        'type'  => $ride['display_name'] ?? 'Uber',
                        'price' => $ride['estimate'] ?? 'N/A',
                        'eta'   => (($ride['duration'] ?? 0) / 60) . ' min',
                    ];
                })->values()->all(),
            ];
        } catch (\Exception $e) {
            return [];
        }
    }
}
