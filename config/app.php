<?php
declare(strict_types=1);

/**
 * Główna konfiguracja aplikacji. Wartości pochodzą z pliku .env
 * (funkcja env() zwraca wartość domyślną, gdy zmiennej brak).
 */
return [
    'name'     => 'PGM – Płockie Gry Miejskie',
    'env'      => env('APP_ENV', 'production'),
    'debug'    => env('APP_DEBUG', false),
    'url'      => rtrim((string) env('APP_URL', ''), '/'),
    'timezone' => 'Europe/Warsaw',
    'locale'   => 'pl',

    'session' => [
        'name'             => 'PGMSESSID',
        'lifetime_minutes' => (int) env('SESSION_LIFETIME_MINUTES', 120),
    ],

    'auth' => [
        'registration_enabled'  => env('REGISTRATION_ENABLED', false),
        'min_password_length'   => 12,
        // limit prób logowania w oknie czasowym
        'max_attempts_per_email' => 5,
        'max_attempts_per_ip'    => 20,
        'attempt_window_minutes' => 15,
    ],

    'map' => [
        'center_lat'  => (float) env('MAP_CENTER_LAT', 52.5463),
        'center_lng'  => (float) env('MAP_CENTER_LNG', 19.6865),
        'zoom'        => (int) env('MAP_DEFAULT_ZOOM', 15),
        'tile_url'    => env('MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        'geocoder'    => env('GEOCODER_URL', 'https://nominatim.openstreetmap.org/search'),
    ],

    // Aplikacja mobilna (gracze)
    'player' => [
        'upload_max_bytes'           => (int) env('PLAYER_UPLOAD_MAX_MB', 30) * 1024 * 1024,
        'max_new_players_per_ip_hour' => (int) env('PLAYER_MAX_NEW_PER_IP_HOUR', 30),
        // ile metrów niedokładności GPS doliczamy do promienia punktu (najwyżej)
        'gps_tolerance_max_m'        => (int) env('PLAYER_GPS_TOLERANCE_MAX_M', 30),
        // ile gier gracz może ROZPOCZĄĆ w ciągu dnia (Europe/Warsaw); wznowienie się nie liczy
        'daily_game_limit'           => (int) env('PLAYER_DAILY_GAME_LIMIT', 2),
    ],

    'uploads' => [
        'path'      => BASE_PATH . '/storage/uploads',
        'max_bytes' => (int) env('UPLOAD_MAX_MB', 5) * 1024 * 1024,
        'mime'      => ['image/jpeg', 'image/png', 'image/webp'],
    ],

    // Content-Security-Policy – ogranicza źródła skryptów i stylów (ochrona przed XSS)
    'csp' => implode('; ', [
        "default-src 'self'",
        "script-src 'self' https://unpkg.com",
        "style-src 'self' https://fonts.googleapis.com https://unpkg.com",
        "font-src 'self' https://fonts.gstatic.com",
        "img-src 'self' data: https:",
        "connect-src 'self' https://nominatim.openstreetmap.org",
        "frame-ancestors 'none'",
        "form-action 'self'",
        "base-uri 'self'",
        "object-src 'none'",
    ]),
];
