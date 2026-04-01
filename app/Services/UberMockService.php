<?php

namespace App\Services;

class UberMockService
{
    public function getEstimates($startLat, $startLng, $endLat, $endLng): array
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
