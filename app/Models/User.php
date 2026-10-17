<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Konta twórców gier i administratorów (tabela users). */
final class User
{
    /** Kolumny bezpieczne do użycia w aplikacji – bez hasha hasła. */
    private const PUBLIC_COLUMNS = 'id, organization_name, first_name, last_name, email, role,
        account_status, last_login_at, created_at, updated_at';

    public static function findById(int $id): ?array
    {
        $row = Database::query('SELECT ' . self::PUBLIC_COLUMNS . ' FROM users WHERE id = ?', [$id])->fetch();
        return $row ?: null;
    }

    /** Zwraca użytkownika RAZEM z password_hash – tylko do weryfikacji logowania. */
    public static function findForLogin(string $email): ?array
    {
        $row = Database::query(
            'SELECT id, email, password_hash, role, account_status FROM users WHERE email = ?',
            [self::normalizeEmail($email)]
        )->fetch();
        return $row ?: null;
    }

    public static function emailExists(string $email): bool
    {
        return (bool) Database::query('SELECT 1 FROM users WHERE email = ?', [self::normalizeEmail($email)])->fetchColumn();
    }

    /**
     * Tworzy konto. Hasło jest od razu hashowane – nigdy nie trafia do bazy jawnie.
     * @param array{organization_name:string, first_name:string, last_name:string, email:string,
     *              password:string, role:string, account_status:string, approved_by?:?int} $data
     */
    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO users (organization_name, first_name, last_name, email, password_hash, role,
                                account_status, approved_by, approved_at, password_changed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $data['organization_name'],
                $data['first_name'],
                $data['last_name'],
                self::normalizeEmail($data['email']),
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['role'],
                $data['account_status'],
                $data['approved_by'] ?? null,
                $data['account_status'] === 'active' ? date('Y-m-d H:i:s') : null,
            ]
        );
        return (int) Database::connection()->lastInsertId();
    }

    public static function recordLogin(int $id): void
    {
        Database::query('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    /** Aktualizacja hasha, gdy PHP zaleca silniejszy algorytm (password_needs_rehash). */
    public static function updatePasswordHash(int $id, string $plainPassword): void
    {
        Database::query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($plainPassword, PASSWORD_DEFAULT), $id]);
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function initials(array $user): string
    {
        return mb_strtoupper(mb_substr($user['first_name'] ?? '', 0, 1) . mb_substr($user['last_name'] ?? '', 0, 1));
    }

    public static function fullName(array $user): string
    {
        return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    }
}
