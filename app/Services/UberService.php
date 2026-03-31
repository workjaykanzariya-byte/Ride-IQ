<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class UberService
{
    private const BASE_URL = 'https://api.uber.com';

    public function getProducts(float $lat, float $lng): array
    {
        return $this->get('/v1.2/products', [
            'latitude' => $lat,
            'longitude' => $lng,
        ]);
    }

    public function getPriceEstimates(float $startLat, float $startLng, float $endLat, float $endLng): array
    {
        return $this->get('/v1.2/estimates/price', [
            'start_latitude' => $startLat,
            'start_longitude' => $startLng,
            'end_latitude' => $endLat,
            'end_longitude' => $endLng,
        ]);
    }

    public function getTimeEstimates(float $lat, float $lng): array
    {
        return $this->get('/v1.2/estimates/time', [
            'start_latitude' => $lat,
            'start_longitude' => $lng,
        ]);
    }

    /**
     * @return array{provider: string, rides: array<int, array{type: string, price: string, eta: string}>}|array{}
     */
    public function getEstimates(float $startLat, float $startLng, float $endLat, float $endLng): array
    {
        try {
            $products = $this->getProducts($startLat, $startLng);
            $prices = $this->getPriceEstimates($startLat, $startLng, $endLat, $endLng);
            $times = $this->getTimeEstimates($startLat, $startLng);

            $priceMap = collect($prices['prices'] ?? [])->keyBy('product_id');
            $timeMap = collect($times['times'] ?? [])->keyBy('product_id');

            $rides = collect($products['products'] ?? [])
                ->map(function (array $product) use ($priceMap, $timeMap): ?array {
                    $productId = $product['product_id'] ?? null;

                    if (! $productId) {
                        return null;
                    }

                    $price = $priceMap->get($productId, []);
                    $eta = $timeMap->get($productId, []);

                    return [
                        'type' => $product['display_name'] ?? 'Unknown',
                        'price' => $price['estimate'] ?? 'N/A',
                        'eta' => isset($eta['estimate']) ? (int) ceil($eta['estimate'] / 60).' min' : 'N/A',
                    ];
                })
                ->filter()
                ->values()
                ->all();

            return [
                'provider' => 'uber',
                'rides' => $rides,
            ];
        } catch (Throwable) {
            return [];
        }
    }

    private function get(string $endpoint, array $query): array
    {
        $response = Http::baseUrl(self::BASE_URL)
            ->acceptJson()
            ->withToken((string) config('services.uber.token'))
            ->get($endpoint, $query);

        if (! $response->successful()) {
            throw new \RuntimeException('Uber API request failed.');
        }

        return $response->json() ?? [];
    }
}
