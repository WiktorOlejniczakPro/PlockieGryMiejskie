<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\HttpException;
use App\Core\Request;
use App\Services\WalkingRouteService;

/**
 * POST /api/v1/admin/route – trasa piesza dla punktów z edytora.
 * Wejście: {"points": [[lat, lng], ...]} (2–200 punktów, w kolejności trasy).
 * Wyjście: {"data": {"distance": m, "duration": s, "geometry": [[lat, lng]...], "snapped": [[lat, lng]...]}}
 */
final class RouteApi
{
    private const MAX_POINTS = 200;

    public function walking(Request $request): void
    {
        $service = WalkingRouteService::fromConfig();
        if ($service === null) {
            throw new HttpException(404, 'Wyznaczanie tras pieszych jest wyłączone (ROUTING_URL w .env).');
        }

        $raw = $request->input('points');
        if (!is_array($raw) || count($raw) < 2 || count($raw) > self::MAX_POINTS) {
            throw new HttpException(422, 'Do wyznaczenia trasy potrzeba od 2 do ' . self::MAX_POINTS . ' punktów.');
        }

        $points = [];
        foreach (array_values($raw) as $p) {
            $lat = is_array($p) && is_numeric($p[0] ?? null) ? (float) $p[0] : null;
            $lng = is_array($p) && is_numeric($p[1] ?? null) ? (float) $p[1] : null;
            if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                throw new HttpException(422, 'Nieprawidłowe współrzędne punktu trasy.');
            }
            $points[] = [$lat, $lng];
        }

        try {
            $route = $service->route($points);
        } catch (\RuntimeException $e) {
            error_log('[PGM] Trasa piesza: ' . $e->getMessage());
            json_response(['error' => [
                'status'  => 502,
                'message' => 'Nie udało się wyznaczyć trasy pieszej (usługa tras jest niedostępna lub nie znalazła drogi). Pokazano linię prostą.',
            ]], 502);
        }

        json_response(['data' => [
            'distance' => round($route['distance'], 1),
            'duration' => round($route['duration']),
            'geometry' => $route['geometry'],
            'snapped'  => $route['snapped'],
        ]]);
    }
}
