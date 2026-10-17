<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Ograniczenie liczby nieudanych logowań – osobno dla adresu e-mail
 * i dla adresu IP, w przesuwnym oknie czasowym (domyślnie 15 minut).
 * Adres e-mail zapisujemy wyłącznie jako skrót SHA-256.
 */
final class RateLimiter
{
    public static function emailHash(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)));
    }

    public static function tooManyAttempts(string $emailHash, string $ip): bool
    {
        $window = (int) config('app.auth.attempt_window_minutes', 15);
        $maxEmail = (int) config('app.auth.max_attempts_per_email', 5);
        $maxIp = (int) config('app.auth.max_attempts_per_ip', 20);

        // Nieudane próby na to konto od ostatniego udanego logowania (w oknie czasowym)
        $emailFailures = (int) Database::query(
            "SELECT COUNT(*) FROM login_attempts
              WHERE email_hash = ? AND succeeded = 0
                AND attempted_at > NOW() - INTERVAL ? MINUTE
                AND attempted_at > COALESCE((SELECT MAX(attempted_at) FROM login_attempts
                                              WHERE email_hash = ? AND succeeded = 1), '1970-01-01')",
            [$emailHash, $window, $emailHash]
        )->fetchColumn();

        $ipFailures = (int) Database::query(
            'SELECT COUNT(*) FROM login_attempts
              WHERE ip_address = INET6_ATON(?) AND succeeded = 0
                AND attempted_at > NOW() - INTERVAL ? MINUTE',
            [$ip, $window]
        )->fetchColumn();

        return $emailFailures >= $maxEmail || $ipFailures >= $maxIp;
    }

    public static function record(string $emailHash, string $ip, bool $succeeded): void
    {
        Database::query(
            'INSERT INTO login_attempts (email_hash, ip_address, succeeded) VALUES (?, INET6_ATON(?), ?)',
            [$emailHash, $ip, $succeeded ? 1 : 0]
        );
    }
}
