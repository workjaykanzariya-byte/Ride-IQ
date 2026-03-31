<?php

namespace App\Services;

class MockLocationService implements LocationServiceInterface
{
    public function getCoordinates(string $place): array
    {
        return [
            'lat' => 23.0763962,
            'lng' => 72.6309997,
        ];
    }
}
