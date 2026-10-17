<?php
declare(strict_types=1);

/**
 * Jedyny punkt wejścia aplikacji (front controller).
 * Apache przekierowuje tu wszystkie żądania (public/.htaccess).
 */

// Wbudowany serwer PHP (php -S): pliki statyczne wydaje bezpośrednio.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

App\Core\App::run();
