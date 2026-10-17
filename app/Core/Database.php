<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Jedno połączenie PDO na żądanie. Wyjątki zamiast cichych błędów,
 * prawdziwe prepared statements (EMULATE_PREPARES = false).
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $c = config('database');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'], $c['database'], $c['charset']);

        try {
            self::$pdo = new PDO($dsn, $c['username'], $c['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            // Nie przekazujemy dalej komunikatu PDO – może zawierać login lub host.
            error_log('[PGM] Błąd połączenia z bazą: ' . $e->getMessage());
            throw new RuntimeException('Nie można połączyć się z bazą danych.', 0);
        }

        return self::$pdo;
    }

    /** Wykonuje zapytanie z parametrami i zwraca przygotowany statement. */
    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Uruchamia funkcję w transakcji; przy wyjątku wycofuje zmiany.
     * @template T
     * @param callable(PDO): T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
