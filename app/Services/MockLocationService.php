<?php

namespace App\Services;

class MockLocationService implements LocationServiceInterface
{
    public function getCoordinates(string $place): array
    {
        $map = [
            'Ahmedabad Airport' => ['lat' => 23.0763962, 'lng' => 72.6309997],
            'Iscon Mall'        => ['lat' => 23.0300,    'lng' => 72.5100],
        ];

        return $map[$place] ?? ['lat' => 23.0225, 'lng' => 72.5714];
    }
}
