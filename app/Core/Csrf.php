<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Tokeny CSRF: jeden losowy token na sesję, porównywany w stałym czasie.
 * Formularze wysyłają go w polu _csrf, zapytania fetch w nagłówku X-CSRF-Token.
 */
final class Csrf
{
    public static function token(): string
    {
        $token = Session::get('_csrf_token');
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            Session::set('_csrf_token', $token);
        }
        return $token;
    }

    public static function verify(?string $token): bool
    {
        $expected = Session::get('_csrf_token');
        return is_string($expected) && is_string($token) && $token !== '' && hash_equals($expected, $token);
    }

    /** Nowy token, np. po zalogowaniu i wylogowaniu. */
    public static function rotate(): void
    {
        Session::forget('_csrf_token');
        self::token();
    }
}
