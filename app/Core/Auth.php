<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Stałe konto – bez logowania.
 * Każde żądanie działa jako konto, którego e-mail wpisano w .env (AUTO_LOGIN_EMAIL).
 * UWAGA: każdy, kto otworzy stronę, ma pełny dostęp do panelu tego konta.
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

        $email = (string) config('app.auth.auto_login_email', '');
        if ($email === '') {
            return null;
        }

        $found = User::findForLogin($email);
        if ($found === null || $found['account_status'] !== 'active') {
            return null;
        }
        return self::$user = User::findById((int) $found['id']);
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
}