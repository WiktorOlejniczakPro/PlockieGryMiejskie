<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Wyznaczanie trasy PIESZEJ po ulicach i chodnikach przez usługę OSRM
 * (domyślnie publiczny serwer FOSSGIS: routing.openstreetmap.de, profil "foot").
 *
 * Zasady usługi FOSSGIS: najwyżej 1 zapytanie na sekundę, poprawny User-Agent,
 * atrybucja na mapie, bez intensywnego użycia. Dlatego:
 *  - zapytania idą przez serwer (nie z przeglądarki) z podpisem aplikacji,
 *  - wyniki są zapisywane w storage/cache/routes (ta sama trasa = brak zapytania),
 *  - globalna blokada pilnuje odstępu co najmniej 1 s między zapytaniami.
 */
final class WalkingRouteService
{
    private const CHUNK = 50;           // ile punktów w jednym zapytaniu do OSRM
    private const TIMEOUT = 10;         // sekundy
    private const MIN_INTERVAL_MS = 1100;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $cacheDir,
        private readonly int $cacheDays = 30,
    ) {
    }

    public static function fromConfig(): ?self
    {
        $url = rtrim((string) config('app.map.routing_url', ''), '/');
        if ($url === '') {
            return null; // trasowanie wyłączone w .env
        }
        return new self($url, BASE_PATH . '/storage/cache/routes', (int) config('app.map.routing_cache_days', 30));
    }

    /**
     * @param list<array{0: float, 1: float}> $points [[lat, lng], ...] w kolejności trasy
     * @return array{distance: float, duration: float, geometry: list<array{0: float, 1: float}>,
     *               snapped: list<array{0: float, 1: float}>}
     */
    public function route(array $points): array
    {
        $distance = 0.0;
        $duration = 0.0;
        $geometry = [];
        $snapped = [];

        // Długie trasy dzielimy na fragmenty (z jednym wspólnym punktem) i sklejamy
        $count = count($points);
        for ($start = 0; $start < $count - 1; $start += self::CHUNK - 1) {
            $chunk = array_slice($points, $start, self::CHUNK);
            $part = $this->routeChunk($chunk);

            $distance += $part['distance'];
            $duration += $part['duration'];
            if ($geometry) {
                array_shift($part['geometry']); // wspólny punkt styku fragmentów
            }
            $geometry = array_merge($geometry, $part['geometry']);
            $snapped = $snapped ? array_merge($snapped, array_slice($part['snapped'], 1)) : $part['snapped'];
        }

        return compact('distance', 'duration', 'geometry', 'snapped');
    }

    private function routeChunk(array $points): array
    {
        $coords = implode(';', array_map(
            static fn (array $p): string => sprintf('%.6F,%.6F', $p[1], $p[0]), // OSRM: lng,lat
            $points
        ));
        $url = $this->baseUrl . '/' . $coords . '?overview=full&geometries=geojson&steps=false';

        $cacheFile = $this->cacheDir . '/' . sha1($url) . '.json';
        if (is_file($cacheFile) && filemtime($cacheFile) > time() - $this->cacheDays * 86400) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $body = $this->fetch($url);
        $json = json_decode($body, true);
        if (!is_array($json) || ($json['code'] ?? '') !== 'Ok' || empty($json['routes'][0]['geometry']['coordinates'])) {
            $code = is_array($json) ? (string) ($json['code'] ?? 'brak') : 'nie-JSON';
            throw new RuntimeException("Usługa tras zwróciła błąd ({$code}).");
        }

        $route = $json['routes'][0];
        $result = [
            'distance' => (float) $route['distance'],
            'duration' => (float) $route['duration'],
            // OSRM zwraca [lng, lat] – zamieniamy na [lat, lng] dla Leafleta
            'geometry' => array_map(static fn (array $c): array => [round((float) $c[1], 6), round((float) $c[0], 6)],
                $route['geometry']['coordinates']),
            'snapped'  => array_map(static fn (array $w): array => [round((float) $w['location'][1], 6), round((float) $w['location'][0], 6)],
                $json['waypoints'] ?? []),
        ];

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }
        @file_put_contents($cacheFile, json_encode($result), LOCK_EX);

        return $result;
    }

    private function fetch(string $url): string
    {
        $this->throttle();
        $agent = 'PGM-PlockieGryMiejskie/1.0 (panel tworcow gier miejskich; PHP)';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_USERAGENT      => $agent,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                CURLOPT_FOLLOWLOCATION => false,
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($body === false || $status !== 200) {
                throw new RuntimeException('Brak odpowiedzi usługi tras (HTTP ' . $status . ($error ? ', ' . $error : '') . ').');
            }
            return (string) $body;
        }

        $context = stream_context_create(['http' => [
            'timeout'       => self::TIMEOUT,
            'header'        => "User-Agent: {$agent}\r\nAccept: application/json\r\n",
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        if ($body === false || !str_contains($statusLine, ' 200')) {
            throw new RuntimeException('Brak odpowiedzi usługi tras (' . ($statusLine ?: 'brak połączenia') . ').');
        }
        return $body;
    }

    /** Co najmniej ~1 s odstępu między zapytaniami z całego serwera (zasady FOSSGIS). */
    private function throttle(): void
    {
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }
        $lock = @fopen($this->cacheDir . '/.throttle', 'c+');
        if ($lock === false) {
            return;
        }
        flock($lock, LOCK_EX);
        $last = (int) stream_get_contents($lock);
        $now = (int) round(microtime(true) * 1000);
        $wait = $last + self::MIN_INTERVAL_MS - $now;
        if ($wait > 0) {
            usleep(min($wait, self::MIN_INTERVAL_MS) * 1000);
        }
        ftruncate($lock, 0);
        rewind($lock);
        fwrite($lock, (string) (int) round(microtime(true) * 1000));
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
