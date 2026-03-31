<?php

namespace App\Services;

interface LocationServiceInterface
{
    /**
     * @return array{lat: float, lng: float}
     */
    public function getCoordinates(string $place): array;
}
