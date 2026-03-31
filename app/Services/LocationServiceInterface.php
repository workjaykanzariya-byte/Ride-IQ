<?php

namespace App\Services;

interface LocationServiceInterface
{
    public function getCoordinates(string $place): array;
}
