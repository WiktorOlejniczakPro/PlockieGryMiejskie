<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Game;
use PDO;

/**
 * Gry w postaci dla aplikacji mobilnej (publiczne API).
 * Tylko gry opublikowane i dostępne w terminie. NIGDY nie zwraca poprawnych
 * odpowiedzi: expected_answer, evaluation_instructions ani is_correct opcji.
 */
final class PlayerGameSerializer
{
    /** Warunek „gra widoczna dla graczy” – ten sam w liście i w szczegółach. */
    public const VISIBLE = "g.status = 'published'
        AND (g.available_from  IS NULL OR g.available_from  <= NOW())
        AND (g.available_until IS NULL OR g.available_until >= NOW())";

    /**
     * Lista gier z filtrami: kategoria (slug), trudność, wyszukiwany tekst.
     * @return list<array>
     */
    public static function list(?string $category, ?string $difficulty, ?string $search): array
    {
        $where = [self::VISIBLE];
        $params = [];
        if ($category !== null && $category !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM game_categories gc JOIN categories c ON c.id = gc.category_id
                                 WHERE gc.game_id = g.id AND c.slug = ?)';
            $params[] = $category;
        }
        if (in_array($difficulty, GameValidationService::DIFFICULTIES, true)) {
            $where[] = 'g.difficulty = ?';
            $params[] = $difficulty;
        }
        if ($search !== null && trim($search) !== '') {
            $where[] = '(g.title LIKE ? OR g.short_description LIKE ?)';
            $like = '%' . addcslashes(mb_substr(trim($search), 0, 100), '%_\\') . '%';
            array_push($params, $like, $like);
        }

        $games = Database::query(
            'SELECT g.id, g.title, g.short_description, g.difficulty, g.estimated_duration_minutes,
                    g.completion_points, g.is_sample, g.published_at
               FROM games g
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY g.published_at DESC, g.id DESC
              LIMIT 200',
            $params
        )->fetchAll();
        if (!$games) {
            return [];
        }

        $ids = array_map('intval', array_column($games, 'id'));
        $categories = self::categoriesFor($ids);
        $waypoints = self::waypointsFor($ids);
        $maxPoints = self::taskPointsFor($ids);

        return array_map(static function (array $g) use ($categories, $waypoints, $maxPoints): array {
            $id = (int) $g['id'];
            $route = $waypoints[$id] ?? [];
            return self::summary($g, $categories[$id] ?? [], $route, $maxPoints[$id] ?? 0);
        }, $games);
    }

    /** Pełne dane gry do rozgrywki albo null (nie istnieje / nieopublikowana). */
    public static function details(int $gameId): ?array
    {
        $game = Database::query(
            'SELECT g.id, g.title, g.short_description, g.description, g.difficulty, g.estimated_duration_minutes,
                    g.completion_points, g.is_sample, g.published_at, g.area_geojson, g.custom_category
               FROM games g WHERE g.id = ? AND ' . self::VISIBLE,
            [$gameId]
        )->fetch();
        if (!$game) {
            return null;
        }

        $waypoints = self::waypointsFor([$gameId])[$gameId] ?? [];
        $tasks = [];
        $options = [];
        if ($waypoints) {
            $ids = array_column($waypoints, 'id');
            $marks = implode(',', array_fill(0, count($ids), '?'));
            foreach (Database::query(
                "SELECT id, waypoint_id, title, description, task_type, hint, max_points, media_requirement,
                        media_max_seconds, unlock_mode, unlock_radius_m
                   FROM tasks WHERE waypoint_id IN ({$marks})",
                $ids
            )->fetchAll() as $task) {
                $tasks[(int) $task['waypoint_id']] = $task;
            }
            if ($tasks) {
                $taskIds = array_column($tasks, 'id');
                $marks = implode(',', array_fill(0, count($taskIds), '?'));
                foreach (Database::query(
                    "SELECT id, task_id, option_text FROM task_options WHERE task_id IN ({$marks}) ORDER BY task_id, position",
                    $taskIds
                )->fetchAll() as $o) {
                    $options[(int) $o['task_id']][] = ['id' => (int) $o['id'], 'text' => $o['option_text']];
                }
            }
        }

        $taskPoints = array_sum(array_map(fn ($t) => (int) $t['max_points'], $tasks));
        $data = self::summary($game, self::categoriesFor([$gameId])[$gameId] ?? [], $waypoints, $taskPoints);
        $data['description'] = $game['description'] ?? '';
        $data['custom_category'] = $game['custom_category'];
        $data['area'] = $game['area_geojson'] !== null ? json_decode((string) $game['area_geojson'], true) : null;
        $data['waypoints'] = array_map(static function (array $wp) use ($tasks, $options): array {
            $task = $tasks[(int) $wp['id']] ?? null;
            return [
                'id'          => (int) $wp['id'],
                'position'    => (int) $wp['position'],
                'name'        => $wp['name'],
                'description' => $wp['description'] ?? '',
                'player_hint' => $wp['player_hint'] ?? '',
                'latitude'    => (float) $wp['latitude'],
                'longitude'   => (float) $wp['longitude'],
                'arrival_radius_m' => $task !== null ? (int) $task['unlock_radius_m'] : GamePlayService::DEFAULT_RADIUS_M,
                'task'        => $task === null ? null : [
                    'id'                => (int) $task['id'],
                    'task_type'         => $task['task_type'],
                    'title'             => $task['title'],
                    'description'       => $task['description'],
                    'hint'              => $task['hint'] ?? '',
                    'max_points'        => (int) $task['max_points'],
                    'media_requirement' => $task['media_requirement'] ?? '',
                    'media_max_seconds' => $task['media_max_seconds'] !== null ? (int) $task['media_max_seconds'] : null,
                    'unlock_mode'       => $task['unlock_mode'],
                    'options'           => $options[(int) $task['id']] ?? [],
                ],
            ];
        }, $waypoints);

        return $data;
    }

    private static function summary(array $g, array $categories, array $route, int $taskPoints): array
    {
        $first = $route[0] ?? null;
        return [
            'id'                         => (int) $g['id'],
            'title'                      => Game::displayTitle($g),
            'short_description'          => $g['short_description'] ?? '',
            'difficulty'                 => $g['difficulty'],
            'estimated_duration_minutes' => $g['estimated_duration_minutes'] !== null ? (int) $g['estimated_duration_minutes'] : null,
            'completion_points'          => (int) $g['completion_points'],
            'max_score'                  => $taskPoints + (int) $g['completion_points'],
            'is_sample'                  => (bool) $g['is_sample'],
            'published_at'               => $g['published_at'],
            'categories'                 => $categories,
            'waypoint_count'             => count($route),
            'route_length_m'             => Geo::polylineMeters($route),
            'start'                      => $first ? ['latitude' => (float) $first['latitude'], 'longitude' => (float) $first['longitude']] : null,
        ];
    }

    /** @return array<int, list<array{slug:string, name:string}>> */
    private static function categoriesFor(array $gameIds): array
    {
        $marks = implode(',', array_fill(0, count($gameIds), '?'));
        $out = [];
        foreach (Database::query(
            "SELECT gc.game_id, c.slug, c.name FROM game_categories gc JOIN categories c ON c.id = gc.category_id
              WHERE gc.game_id IN ({$marks}) ORDER BY c.position",
            $gameIds
        )->fetchAll() as $row) {
            $out[(int) $row['game_id']][] = ['slug' => $row['slug'], 'name' => $row['name']];
        }
        return $out;
    }

    /** @return array<int, list<array>> punkty trasy pogrupowane po grze, w kolejności */
    private static function waypointsFor(array $gameIds): array
    {
        $marks = implode(',', array_fill(0, count($gameIds), '?'));
        $out = [];
        foreach (Database::query(
            "SELECT id, game_id, name, description, player_hint, latitude, longitude, position
               FROM waypoints WHERE game_id IN ({$marks}) ORDER BY game_id, position",
            $gameIds
        )->fetchAll() as $row) {
            $out[(int) $row['game_id']][] = $row;
        }
        return $out;
    }

    /** @return array<int, int> suma max_points zadań w grze */
    private static function taskPointsFor(array $gameIds): array
    {
        $marks = implode(',', array_fill(0, count($gameIds), '?'));
        return array_map('intval', Database::query(
            "SELECT w.game_id, COALESCE(SUM(t.max_points), 0) FROM waypoints w JOIN tasks t ON t.waypoint_id = w.id
              WHERE w.game_id IN ({$marks}) GROUP BY w.game_id",
            $gameIds
        )->fetchAll(PDO::FETCH_KEY_PAIR));
    }
}
