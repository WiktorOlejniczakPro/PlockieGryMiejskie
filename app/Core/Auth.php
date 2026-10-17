<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Stan zalogowania. Użytkownik jest pobierany z bazy przy każdym żądaniu,
 * więc zablokowanie konta lub zmiana roli działa natychmiast.
 */
final class Auth
{
    private static ?array $user = null;
    private static bool $resolved = false;

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;

        $id = Session::get('user_id');
        if (!is_int($id)) {
            return self::$user = self::autoLogin();
        }

        $user = User::findById($id);
        if ($user === null || $user['account_status'] !== 'active') {
            self::logout();
            return null;
        }
        return self::$user = $user;
    }

    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? null) === 'admin';
    }

    /** Czy włączony jest tryb demonstracyjny z automatycznym logowaniem. */
    public static function autoLoginEnabled(): bool
    {
        return config('app.auth.auto_login_email', '') !== '' && config('app.env') !== 'production';
    }

    /**
     * TRYB DEMONSTRACYJNY: loguje konto z AUTO_LOGIN_EMAIL, jeśli nikt nie jest zalogowany.
     * Nie działa w APP_ENV=production ani po ręcznym wylogowaniu (do zamknięcia przeglądarki).
     */
    private static function autoLogin(): ?array
    {
        if (!self::autoLoginEnabled()) {
            if (config('app.auth.auto_login_email', '') !== '') {
                error_log('[PGM] AUTO_LOGIN_EMAIL jest ustawiony, ale ignorowany w trybie production.');
            }
            return null;
        }
        if (Session::get('_no_auto_login') === true) {
            return null;
        }

        $found = User::findForLogin((string) config('app.auth.auto_login_email'));
        if ($found === null || $found['account_status'] !== 'active') {
            error_log('[PGM] AUTO_LOGIN_EMAIL: nie znaleziono aktywnego konta o tym adresie.');
            return null;
        }

        Session::regenerate();
        Session::set('user_id', (int) $found['id']);
        Session::set('logged_in_at', time());
        return User::findById((int) $found['id']);
    }

    public static function login(array $user): void
    {
        Session::forget('_no_auto_login');
        Session::regenerate();
        Csrf::rotate();
        Session::set('user_id', (int) $user['id']);
        Session::set('logged_in_at', time());
        self::$user = $user;
        self::$resolved = true;
    }

    public static function logout(): void
    {
        Session::forget('user_id');
        Session::forget('logged_in_at');
        Session::regenerate();
        Csrf::rotate();
        self::$user = null;
        self::$resolved = true;
    }
}
