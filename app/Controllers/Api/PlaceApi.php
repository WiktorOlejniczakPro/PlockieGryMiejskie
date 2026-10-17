<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Services\Geo;

/**
 * Miejsca Płocka z otwartych danych miasta (zakładka „Odkrywaj” w aplikacji).
 *   GET /api/v1/place-categories                          – rodzaje miejsc z liczbą obiektów
 *   GET /api/v1/places?lat=&lng=&radius_m=&category=&q=   – miejsca najbliżej podanego punktu
 * Bez punktu (lat/lng) środkiem jest centrum Płocka.
 */
final class PlaceApi
{
    private const MAX_RADIUS_M = 5000;
    private const LIMIT = 300;

    public function categories(Request $request): void
    {
        $rows = Database::query(
            'SELECT c.slug, c.name, c.source_name, COUNT(p.id) AS place_count
               FROM place_categories c
               LEFT JOIN places p ON p.category_id = c.id AND p.is_active = 1
              WHERE c.is_active = 1
              GROUP BY c.id, c.slug, c.name, c.source_name, c.position
              ORDER BY c.position'
        )->fetchAll();

        json_response(['data' => array_map(static fn (array $r): array => [
            'slug'        => $r['slug'],
            'name'        => $r['name'],
            'source_name' => $r['source_name'],
            'place_count' => (int) $r['place_count'],
        ], $rows)]);
    }

    public function index(Request $request): void
    {
        $lat = self::floatParam($request, 'lat') ?? (float) config('app.map.center_lat', 52.5463);
        $lng = self::floatParam($request, 'lng') ?? (float) config('app.map.center_lng', 19.6865);
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new HttpException(422, 'Nieprawidłowe współrzędne.');
        }
        $radius = (int) min(max(self::floatParam($request, 'radius_m') ?? 1500, 50), self::MAX_RADIUS_M);

        // Prostokąt wokół punktu (szybki filtr po indeksie), dokładna odległość liczona niżej
        $dLat = $radius / 111320;
        $dLng = $radius / (111320 * max(cos(deg2rad($lat)), 0.01));
        $where = ['p.is_active = 1', 'c.is_active = 1', 'p.latitude BETWEEN ? AND ?', 'p.longitude BETWEEN ? AND ?'];
        $params = [$lat - $dLat, $lat + $dLat, $lng - $dLng, $lng + $dLng];

        $category = $request->queryParam('category');
        if (is_string($category) && $category !== '') {
            $slugs = array_slice(array_filter(explode(',', $category)), 0, 30);
            $where[] = 'c.slug IN (' . implode(',', array_fill(0, count($slugs), '?')) . ')';
            array_push($params, ...$slugs);
        }
        $search = $request->queryParam('q');
        if (is_string($search) && trim($search) !== '') {
            $where[] = '(p.name LIKE ? OR p.address LIKE ?)';
            $like = '%' . addcslashes(mb_substr(trim($search), 0, 100), '%_\\') . '%';
            array_push($params, $like, $like);
        }

        $rows = Database::query(
            'SELECT p.id, p.name, p.address, p.description, p.latitude, p.longitude, c.slug, c.name AS category_name
               FROM places p JOIN place_categories c ON c.id = p.category_id
              WHERE ' . implode(' AND ', $where) . '
              LIMIT 2000',
            $params
        )->fetchAll();

        $places = [];
        foreach ($rows as $r) {
            $distance = Geo::distanceMeters($lat, $lng, (float) $r['latitude'], (float) $r['longitude']);
            if ($distance > $radius) {
                continue;
            }
            $places[] = [
                'id'            => (int) $r['id'],
                'category'      => $r['slug'],
                'category_name' => $r['category_name'],
                'name'          => $r['name'],
                'address'       => $r['address'],
                'description'   => $r['description'],
                'latitude'      => (float) $r['latitude'],
                'longitude'     => (float) $r['longitude'],
                'distance_m'    => (int) round($distance),
            ];
        }
        usort($places, static fn (array $a, array $b): int => $a['distance_m'] <=> $b['distance_m']);

        json_response(['data' => array_slice($places, 0, self::LIMIT)]);
    }

    private static function floatParam(Request $request, string $key): ?float
    {
        $value = $request->queryParam($key);
        return is_numeric($value) ? (float) $value : null;
    }
}
