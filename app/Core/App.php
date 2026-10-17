<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Obsługa pojedynczego żądania: nagłówki bezpieczeństwa, sesja,
 * routing i zamiana błędów na czytelne strony lub odpowiedzi JSON.
 */
final class App
{
    public static function run(): void
    {
        self::sendSecurityHeaders();

        $request = null;
        try {
            if (!Env::isLoaded()) {
                throw new \RuntimeException('Brak pliku .env – skopiuj .env.example jako .env i uzupełnij dane bazy.');
            }

            $request = Request::capture();
            if (!$request->isPlayerApi()) {
                Session::start();
            }

            $router = new Router();
            require BASE_PATH . '/routes/web.php';
            $router->dispatch($request);
        } catch (HttpException $e) {
            self::renderError($request, $e->status, $e->getMessage());
        } catch (Throwable $e) {
            error_log('[PGM] ' . get_class($e) . ': ' . $e->getMessage() . ' w ' . $e->getFile() . ':' . $e->getLine());
            $debug = (bool) config('app.debug') && config('app.env') !== 'production';
            self::renderError($request, 500, HttpException::defaultMessage(500), $debug ? $e : null);
        }
    }

    private static function sendSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('Content-Security-Policy: ' . config('app.csp', "default-src 'self'"));
    }

    private static function renderError(?Request $request, int $status, string $message, ?Throwable $debug = null): void
    {
        if (headers_sent() === false) {
            // 419 (wygasły token CSRF) nie jest standardowym kodem HTTP – Apache zamienia go na 500,
            // dlatego do przeglądarki wysyłamy 403, a treść strony mówi o wygaśnięciu sesji.
            http_response_code($status === 419 ? 403 : $status);
        }

        $isApi = $request?->isApi() ?? str_contains((string) ($_SERVER['REQUEST_URI'] ?? ''), '/api/');
        if ($isApi) {
            $payload = ['error' => ['status' => $status, 'message' => $message]];
            if ($debug) {
                $payload['error']['debug'] = $debug->getMessage();
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
            return;
        }

        $titles = [
            403 => 'Brak dostępu', 404 => 'Nie znaleziono strony', 405 => 'Niedozwolona operacja',
            419 => 'Sesja wygasła', 429 => 'Zbyt wiele prób', 500 => 'Coś poszło nie tak',
        ];

        try {
            View::render('errors/error', [
                'pageTitle' => $titles[$status] ?? 'Błąd',
                'status'    => $status,
                'message'   => $message,
                'debug'     => $debug,
            ], 'layouts/minimal');
        } catch (Throwable) {
            // Ostateczność – gdy nie da się wyrenderować nawet strony błędu.
            header('Content-Type: text/plain; charset=utf-8');
            echo "Błąd {$status}: {$message}";
        }
    }
}
