<?php

namespace App\Services;

class UberMockService
{
    /**
     * @return array{provider: string, rides: array<int, array{type: string, price: string, eta: string}>}
     */
    public function getEstimates(float $startLat, float $startLng, float $endLat, float $endLng): array
    {
        return [
            'provider' => 'uber',
            'rides' => [
                [
                    'type' => 'UberGo',
                    'price' => '₹120 - ₹150',
                    'eta' => '4 min',
                ],
                [
                    'type' => 'UberXL',
                    'price' => '₹220 - ₹260',
                    'eta' => '6 min',
                ],
            ],
        ];
    }
}
