<?php

namespace App\Services;

class DistanceService
{
    public function calculate(
        float $latitude1,
        float $longitude1,
        float $latitude2,
        float $longitude2
    ): float {

        $earthRadius = 6371;

        $latDifference = deg2rad(
            $latitude2 - $latitude1
        );

        $lonDifference = deg2rad(
            $longitude2 - $longitude1
        );

        $a =
            sin($latDifference / 2) ** 2
            +
            cos(deg2rad($latitude1))
            *
            cos(deg2rad($latitude2))
            *
            sin($lonDifference / 2) ** 2;

        $c = 2 * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

        return $earthRadius * $c;
    }
}