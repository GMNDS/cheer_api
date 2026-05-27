<?php

namespace Cheer\Services;

final class GeoDistance
{
    public static function between(float $originLat, float $originLng, float $targetLat, float $targetLng): float
    {
        $earthRadiusKm = 6371.0;
        $latFrom = deg2rad($originLat);
        $latTo = deg2rad($targetLat);
        $latDelta = deg2rad($targetLat - $originLat);
        $lngDelta = deg2rad($targetLng - $originLng);

        $a = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2;

        return $earthRadiusKm * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}