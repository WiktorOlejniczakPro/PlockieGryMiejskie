<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Bezpieczna sesja PHP: ciasteczko HttpOnly + SameSite=Lax (+ Secure przy HTTPS),
 * tryb strict (odrzucanie nieznanych identyfikatorów), wygasanie po bezczynności,
 * komunikaty flash i zapamiętywanie danych formularza po błędzie.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');

        session_name((string) config('app.session.name', 'PGMSESSID'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => base_url() !== '' ? base_url() . '/' : '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // Wylogowanie po bezczynności
        $limit = (int) config('app.session.lifetime_minutes', 120) * 60;
        $last = $_SESSION['_last_activity'] ?? null;
        if ($last !== null && time() - (int) $last > $limit) {
            self::destroy();
            session_start();
            self::flash('info', 'Sesja wygasła z powodu braku aktywności. Zaloguj się ponownie.');
        }
        $_SESSION['_last_activity'] = time();

        // Flash: komunikaty z poprzedniego żądania są dostępne teraz, potem znikają.
        $_SESSION['_flash_current'] = $_SESSION['_flash_next'] ?? [];
        $_SESSION['_flash_next'] = [];
        $_SESSION['_old_input'] = $_SESSION['_old_input_next'] ?? [];
        $_SESSION['_old_input_next'] = [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Komunikat widoczny w następnym żądaniu (po przekierowaniu). */
    public static function flash(string $key, string $message): void
    {
        $_SESSION['_flash_next'][$key] = $message;
    }

    /** Zapamiętuje pola formularza (bez haseł) do ponownego wypełnienia. */
    public static function flashInput(array $input): void
    {
        unset($input['password'], $input['password_confirmation'], $input['_csrf']);
        $_SESSION['_old_input_next'] = $input;
    }

    /** Nowy identyfikator sesji – po zalogowaniu (ochrona przed session fixation). */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?: 'Lax',
            ]);
        }
        session_destroy();
    }
}
