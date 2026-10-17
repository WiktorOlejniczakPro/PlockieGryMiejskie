<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Request;

/**
 * Gracze aplikacji mobilnej. Konto zakłada aplikacja przy pierwszym
 * uruchomieniu; gracz identyfikuje się tokenem API (w bazie tylko SHA-256).
 */
final class Player
{
    public const NICKNAME_PATTERN = '/^[\p{L}\p{N} _.\-]{3,40}$/u';

    /** Bieżący gracz (ustawiany przez PlayerMiddleware). */
    private static ?array $current = null;

    /** @return array{0: array, 1: string} [gracz, token w postaci jawnej – zwracany TYLKO raz] */
    public static function create(string $nickname): array
    {
        $token = bin2hex(random_bytes(32));
        Database::query(
            'INSERT INTO players (nickname, api_token_hash, created_ip) VALUES (?, ?, INET6_ATON(?))',
            [$nickname, hash('sha256', $token), Request::ip()]
        );
        $id = (int) Database::connection()->lastInsertId();
        return [self::findById($id), $token];
    }

    public static function findById(int $id): ?array
    {
        $row = Database::query('SELECT id, nickname, created_at FROM players WHERE id = ?', [$id])->fetch();
        return $row ?: null;
    }

    public static function findByToken(string $token): ?array
    {
        $row = Database::query(
            'SELECT id, nickname, created_at, last_seen_at FROM players WHERE api_token_hash = ?',
            [hash('sha256', $token)]
        )->fetch();
        return $row ?: null;
    }

    /** Liczba kont założonych z danego adresu IP w ostatniej godzinie (limit nadużyć). */
    public static function createdRecentlyFromIp(string $ip): int
    {
        return (int) Database::query(
            'SELECT COUNT(*) FROM players WHERE created_ip = INET6_ATON(?) AND created_at > NOW() - INTERVAL 1 HOUR',
            [$ip]
        )->fetchColumn();
    }

    public static function rename(int $id, string $nickname): void
    {
        Database::query('UPDATE players SET nickname = ? WHERE id = ?', [$nickname, $id]);
    }

    /** Aktualizacja "ostatnio widziany" najwyżej raz na 5 minut (bez zapisu przy każdym żądaniu). */
    public static function touch(array $player): void
    {
        $last = $player['last_seen_at'] ?? null;
        if ($last === null || strtotime((string) $last) < time() - 300) {
            Database::query('UPDATE players SET last_seen_at = NOW() WHERE id = ?', [(int) $player['id']]);
        }
    }

    public static function setCurrent(array $player): void
    {
        self::$current = $player;
    }

    public static function current(): array
    {
        if (self::$current === null) {
            throw new \LogicException('Brak gracza – trasa wymaga middleware "player".');
        }
        return self::$current;
    }

    public static function publicData(array $player): array
    {
        return [
            'id'         => (int) $player['id'],
            'nickname'   => $player['nickname'],
            'created_at' => $player['created_at'],
        ];
    }
}
