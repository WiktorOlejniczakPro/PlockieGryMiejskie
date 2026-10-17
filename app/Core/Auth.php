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
            return null;
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

    public static function login(array $user): void
    {
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
