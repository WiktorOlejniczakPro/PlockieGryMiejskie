<?php
declare(strict_types=1);

/** Połączenie z MySQL / MariaDB przez PDO. Dane wyłącznie z pliku .env. */
return [
    'host'     => env('DB_HOST', '127.0.0.1'),
    'port'     => (int) env('DB_PORT', 3306),
    'database' => env('DB_NAME', 'pgm'),
    'username' => env('DB_USER', 'root'),
    'password' => (string) env('DB_PASS', ''),
    'charset'  => 'utf8mb4',
];
