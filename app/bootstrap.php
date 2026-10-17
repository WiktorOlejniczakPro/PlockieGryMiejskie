<?php
declare(strict_types=1);

/**
 * Wspólny start aplikacji – używany przez public/index.php oraz skrypty bin/.
 * Ładuje autoloader, pomocnicze funkcje, .env, konfigurację i obsługę błędów.
 */

// Sprawdzenie wersji PHP PRZED wczytaniem reszty kodu (napisanego dla PHP 8.2).
// Ten plik celowo używa tylko składni zgodnej ze starszymi wersjami PHP.
if (PHP_VERSION_ID < 80200) {
    $pgmMessage = 'PGM wymaga PHP 8.2 lub nowszego. Ta instalacja ma PHP ' . PHP_VERSION . '. '
        . 'Zainstaluj aktualny XAMPP (z PHP 8.2+) ze strony https://www.apachefriends.org';
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'BŁĄD: ' . $pgmMessage . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo $pgmMessage;
    exit;
}

define('BASE_PATH', dirname(__DIR__));

// Prosty autoloader PSR-4: App\Core\Router -> app/Core/Router.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/helpers.php';

App\Core\Env::load(BASE_PATH . '/.env');
App\Core\Config::load(BASE_PATH . '/config');

date_default_timezone_set((string) config('app.timezone', 'Europe/Warsaw'));
mb_internal_encoding('UTF-8');

// Szczegóły błędów tylko w trybie deweloperskim; zawsze zapis do logu.
$debug = (bool) config('app.debug', false) && config('app.env') !== 'production';
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');
