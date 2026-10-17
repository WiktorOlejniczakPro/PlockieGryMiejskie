<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Odczyty gier potrzebne w ETAPIE 2 (strona publiczna i dashboard).
 * Zapis, edycja i publikacja zostaną dodane razem z edytorem.
 */
final class Game
{
    /**
     * Opublikowane gry widoczne publicznie – tylko status "published"
     * i tylko w terminie dostępności (jeśli został ustawiony).
     */
    public static function publishedForPublic(int $limit = 6): array
    {
        $limit = max(1, min(50, $limit));
        return Database::query(
            "SELECT g.id, g.title, g.short_description, g.difficulty, g.estimated_duration_minutes,
                    g.is_sample, g.published_at,
                    (SELECT COUNT(*) FROM waypoints w WHERE w.game_id = g.id) AS waypoint_count,
                    (SELECT GROUP_CONCAT(c.name ORDER BY c.position SEPARATOR ', ')
                       FROM game_categories gc JOIN categories c ON c.id = gc.category_id
                      WHERE gc.game_id = g.id) AS categories
               FROM games g
              WHERE g.status = 'published'
                AND (g.available_from  IS NULL OR g.available_from  <= NOW())
                AND (g.available_until IS NULL OR g.available_until >= NOW())
              ORDER BY g.published_at DESC
              LIMIT {$limit}"
        )->fetchAll();
    }

    /**
     * Liczba gier według statusu. Administrator widzi wszystkie gry,
     * twórca – tylko własne.
     * @return array{total:int, draft:int, published:int, hidden:int, archived:int}
     */
    public static function statusCounts(array $user): array
    {
        [$where, $params] = self::scope($user);
        $rows = Database::query("SELECT status, COUNT(*) AS n FROM games g {$where} GROUP BY status", $params)->fetchAll();

        $counts = ['total' => 0, 'draft' => 0, 'published' => 0, 'hidden' => 0, 'archived' => 0];
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['n'];
            $counts['total'] += (int) $row['n'];
        }
        return $counts;
    }

    /** Ostatnio edytowane gry w zakresie widocznym dla użytkownika. */
    public static function recentlyUpdated(array $user, int $limit = 5): array
    {
        [$where, $params] = self::scope($user);
        $limit = max(1, min(50, $limit));
        return Database::query(
            "SELECT g.id, g.title, g.status, g.difficulty, g.is_sample, g.updated_at,
                    (SELECT COUNT(*) FROM waypoints w WHERE w.game_id = g.id) AS waypoint_count,
                    CONCAT(u.first_name, ' ', u.last_name) AS owner_name
               FROM games g
               JOIN users u ON u.id = g.owner_id
               {$where}
              ORDER BY g.updated_at DESC
              LIMIT {$limit}",
            $params
        )->fetchAll();
    }

    /** Podstawowe dane gry (bez punktów) albo null, gdy gra nie istnieje. */
    public static function findById(int $id): ?array
    {
        $row = Database::query('SELECT * FROM games WHERE id = ?', [$id])->fetch();
        return $row ?: null;
    }

    /** Lista gier widocznych dla użytkownika (administrator – wszystkie). */
    public static function listForUser(array $user): array
    {
        [$where, $params] = self::scope($user);
        return Database::query(
            "SELECT g.id, g.title, g.short_description, g.status, g.difficulty, g.is_sample,
                    g.estimated_duration_minutes, g.updated_at, g.owner_id,
                    CONCAT(u.first_name, ' ', u.last_name) AS owner_name,
                    (SELECT COUNT(*) FROM waypoints w WHERE w.game_id = g.id) AS waypoint_count,
                    (SELECT GROUP_CONCAT(c.name ORDER BY c.position SEPARATOR ', ')
                       FROM game_categories gc JOIN categories c ON c.id = gc.category_id
                      WHERE gc.game_id = g.id) AS categories
               FROM games g
               JOIN users u ON u.id = g.owner_id
               {$where}
              ORDER BY g.updated_at DESC, g.id DESC",
            $params
        )->fetchAll();
    }

    /** Usuwa przedrostek "[PRZYKŁAD]" z tytułu – etykietę pokazujemy osobno. */
    public static function displayTitle(array $game): string
    {
        $title = (string) $game['title'];
        return !empty($game['is_sample']) ? (string) preg_replace('/^\[PRZYKŁAD\]\s*/u', '', $title) : $title;
    }

    /** @return array{0:string, 1:array} fragment WHERE ograniczający gry do uprawnień */
    private static function scope(array $user): array
    {
        if (($user['role'] ?? '') === 'admin') {
            return ['', []];
        }
        return ['WHERE g.owner_id = ?', [(int) $user['id']]];
    }
}
