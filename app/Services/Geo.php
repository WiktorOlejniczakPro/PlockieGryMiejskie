<?php
declare(strict_types=1);

namespace App\Services;

/** Obliczenia geograficzne (WGS84). */
final class Geo
{
    private const EARTH_RADIUS_M = 6371008.8;

    /** Odległość po kuli ziemskiej (wzór haversine) w metrach. */
    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * self::EARTH_RADIUS_M * asin(min(1.0, sqrt($a)));
    }

    /**
     * Długość łamanej przez punkty w kolejności – „w linii prostej”, nie trasa piesza.
     * @param list<array{latitude: float|string, longitude: float|string}> $points
     */
    public static function polylineMeters(array $points): int
    {
        $total = 0.0;
        for ($i = 1, $n = count($points); $i < $n; $i++) {
            $total += self::distanceMeters(
                (float) $points[$i - 1]['latitude'], (float) $points[$i - 1]['longitude'],
                (float) $points[$i]['latitude'], (float) $points[$i]['longitude']
            );
        }
        return (int) round($total);
    }
}
