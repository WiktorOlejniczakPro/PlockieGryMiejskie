<?php
declare(strict_types=1);

/**
 * Globalne funkcje pomocnicze używane w kontrolerach i widokach.
 */

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Request;
use App\Core\Session;

/** Odczyt zmiennej środowiskowej z .env (z konwersją true/false/null). */
function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}

/** Odczyt konfiguracji w notacji kropkowej, np. config('app.map.zoom'). */
function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

/** Escapowanie tekstu do HTML – używaj przy KAŻDYM wypisywaniu danych w widoku. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Bazowy adres aplikacji, np. "/pgm/public" albo "" (gdy w katalogu głównym). */
function base_url(): string
{
    $configured = (string) config('app.url', '');
    if ($configured !== '') {
        return (string) parse_url($configured, PHP_URL_PATH);
    }
    return Request::basePath();
}

/** Adres strony wewnątrz aplikacji: url('/panel') -> "/pgm/public/panel". */
function url(string $path = '/'): string
{
    $path = '/' . ltrim($path, '/');
    return rtrim(base_url(), '/') . ($path === '/' ? '/' : $path);
}

/** Adres pliku z public/assets z numerem wersji (odświeżanie cache po zmianie). */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = BASE_PATH . '/public/assets/' . $path;
    $ver = is_file($file) ? (string) filemtime($file) : '1';
    return url('/assets/' . $path) . '?v=' . $ver;
}

/** Ukryte pole formularza z tokenem CSRF. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

/** Token CSRF (np. do meta tagu dla zapytań fetch). */
function csrf_token(): string
{
    return Csrf::token();
}

/** Poprzednia wartość pola formularza po błędzie walidacji. */
function old(string $key, string $default = ''): string
{
    $old = Session::get('_old_input', []);
    return is_array($old) && isset($old[$key]) ? (string) $old[$key] : $default;
}

/** Komunikat jednorazowy (flash) zapisany w poprzednim żądaniu. */
function flash(string $key): ?string
{
    $messages = Session::get('_flash_current', []);
    return is_array($messages) && isset($messages[$key]) ? (string) $messages[$key] : null;
}

/** Przekierowanie i zakończenie żądania. */
function redirect(string $path, int $status = 302): never
{
    $target = preg_match('#^https?://#', $path) ? $path : url($path);
    header('Location: ' . $target, true, $status);
    exit;
}

/** Odpowiedź JSON i zakończenie żądania. */
function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

/** Polska odmiana: plural_pl(3, 'gra', 'gry', 'gier') -> "gry". */
function plural_pl(int $n, string $one, string $few, string $many): string
{
    if ($n === 1) {
        return $one;
    }
    $last = $n % 10;
    $lastTwo = $n % 100;
    return ($last >= 2 && $last <= 4 && ($lastTwo < 12 || $lastTwo > 14)) ? $few : $many;
}

/** Czas gry w czytelnej formie: 90 -> "1 h 30 min". */
function format_duration(?int $minutes): string
{
    if (!$minutes) {
        return '—';
    }
    if ($minutes < 60) {
        return $minutes . ' min';
    }
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $m ? "{$h} h {$m} min" : "{$h} h";
}

/** Data w formacie polskim: "17.10.2026, 13:40". */
function format_datetime(?string $value): string
{
    if (!$value) {
        return '—';
    }
    $ts = strtotime($value);
    return $ts ? date('d.m.Y, H:i', $ts) : '—';
}

/** Etykiety słownikowe używane w wielu widokach. */
function label(string $group, string $key): string
{
    static $labels = [
        'difficulty' => ['easy' => 'Łatwy', 'medium' => 'Średni', 'hard' => 'Trudny'],
        'status'     => ['draft' => 'Szkic', 'published' => 'Opublikowana', 'hidden' => 'Ukryta', 'archived' => 'Zarchiwizowana'],
        'role'       => ['admin' => 'Administrator', 'creator' => 'Twórca gier'],
    ];
    return $labels[$group][$key] ?? $key;
}
