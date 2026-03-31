<?php

namespace App\Services;

use Exception;

class UberService
{
    public function getEstimates($startLat, $startLng, $endLat, $endLng): array
    {
        try {
            return [];
        } catch (Exception $e) {
            return [];
        }
    }
}
