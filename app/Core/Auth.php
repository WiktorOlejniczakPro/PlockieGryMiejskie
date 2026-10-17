<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Stałe konto – bez logowania.
 * Każde żądanie działa jako konto, którego e-mail wpisano w .env (AUTO_LOGIN_EMAIL).
 * Gdy takiego konta nie ma, panel używa pierwszego aktywnego administratora,
 * a gdy w bazie nie ma żadnego – sam go zakłada (nie trzeba uruchamiać bin/create-admin.php).
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

        // 1) konto z AUTO_LOGIN_EMAIL w .env
        $email = (string) config('app.auth.auto_login_email', '');
        if ($email !== '') {
            $found = User::findForLogin($email);
            if ($found !== null && $found['account_status'] === 'active') {
                return self::$user = User::findById((int) $found['id']);
            }
        }

        // 2) pierwszy aktywny administrator, 3) brak – zakładamy konto administratora
        $id = Database::query(
            "SELECT id FROM users WHERE role = 'admin' AND account_status = 'active' ORDER BY id LIMIT 1"
        )->fetchColumn();
        if ($id === false) {
            Database::query(
                "INSERT INTO users (organization_name, first_name, last_name, email, password_hash, role, account_status)
                 VALUES ('Urząd Miasta Płocka', 'Administrator', 'PGM', ?, '!panel-bez-logowania', 'admin', 'active')",
                [$email !== '' && User::findForLogin($email) === null ? User::normalizeEmail($email) : 'admin@pgm.local']
            );
            $id = Database::connection()->lastInsertId();
        }
        return self::$user = User::findById((int) $id);
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