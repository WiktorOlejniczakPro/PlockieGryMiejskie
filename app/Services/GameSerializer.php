<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Zamiana gry z bazy na strukturę JSON.
 * forEditor() – PEŁNE dane dla edytora w panelu (z poprawnymi odpowiedziami).
 * Publiczne API (kolejny etap) dostanie osobną metodę BEZ poprawnych odpowiedzi.
 */
final class GameSerializer
{
    public static function forEditor(int $gameId): ?array
    {
        $game = Database::query(
            'SELECT id, owner_id, title, short_description, description, estimated_duration_minutes, difficulty,
                    status, completion_points, custom_category, available_from, available_until, is_sample,
                    lock_version, published_at, updated_at
               FROM games WHERE id = ?',
            [$gameId]
        )->fetch();
        if (!$game) {
            return null;
        }

        $categories = Database::query('SELECT category_id FROM game_categories WHERE game_id = ? ORDER BY category_id', [$gameId])
            ->fetchAll(\PDO::FETCH_COLUMN);

        $waypoints = Database::query(
            'SELECT id, name, description, player_hint, latitude, longitude, position
               FROM waypoints WHERE game_id = ? ORDER BY position',
            [$gameId]
        )->fetchAll();

        $tasksByWaypoint = [];
        $optionsByTask = [];
        if ($waypoints) {
            $ids = array_column($waypoints, 'id');
            $marks = implode(',', array_fill(0, count($ids), '?'));
            foreach (Database::query("SELECT * FROM tasks WHERE waypoint_id IN ({$marks})", $ids)->fetchAll() as $task) {
                $tasksByWaypoint[(int) $task['waypoint_id']] = $task;
            }
            if ($tasksByWaypoint) {
                $taskIds = array_column($tasksByWaypoint, 'id');
                $marks = implode(',', array_fill(0, count($taskIds), '?'));
                $rows = Database::query(
                    "SELECT task_id, option_text, is_correct FROM task_options WHERE task_id IN ({$marks}) ORDER BY task_id, position",
                    $taskIds
                )->fetchAll();
                foreach ($rows as $row) {
                    $optionsByTask[(int) $row['task_id']][] = ['text' => $row['option_text'], 'is_correct' => (bool) $row['is_correct']];
                }
            }
        }

        return [
            'id'                         => (int) $game['id'],
            'owner_id'                   => (int) $game['owner_id'],
            'title'                      => $game['title'],
            'short_description'          => $game['short_description'] ?? '',
            'description'                => $game['description'] ?? '',
            'estimated_duration_minutes' => $game['estimated_duration_minutes'] !== null ? (int) $game['estimated_duration_minutes'] : null,
            'difficulty'                 => $game['difficulty'],
            'status'                     => $game['status'],
            'completion_points'          => (int) $game['completion_points'],
            'categories'                 => array_map('intval', $categories),
            'custom_category'            => $game['custom_category'] ?? '',
            'available_from'             => self::localDate($game['available_from']),
            'available_until'            => self::localDate($game['available_until']),
            'is_sample'                  => (bool) $game['is_sample'],
            'lock_version'               => (int) $game['lock_version'],
            'updated_at'                 => $game['updated_at'],
            'waypoints'                  => array_map(static function (array $wp) use ($tasksByWaypoint, $optionsByTask): array {
                $task = $tasksByWaypoint[(int) $wp['id']] ?? null;
                return [
                    'id'          => (int) $wp['id'],
                    'position'    => (int) $wp['position'],
                    'name'        => $wp['name'],
                    'description' => $wp['description'] ?? '',
                    'player_hint' => $wp['player_hint'] ?? '',
                    'latitude'    => (float) $wp['latitude'],
                    'longitude'   => (float) $wp['longitude'],
                    'task'        => $task === null ? null : [
                        'id'                      => (int) $task['id'],
                        'task_type'               => $task['task_type'],
                        'title'                   => $task['title'],
                        'description'             => $task['description'],
                        'hint'                    => $task['hint'] ?? '',
                        'max_points'              => (int) $task['max_points'],
                        'expected_answer'         => $task['expected_answer'] ?? '',
                        'evaluation_instructions' => $task['evaluation_instructions'] ?? '',
                        'media_requirement'       => $task['media_requirement'] ?? '',
                        'media_max_seconds'       => $task['media_max_seconds'] !== null ? (int) $task['media_max_seconds'] : null,
                        'unlock_mode'             => $task['unlock_mode'],
                        'unlock_radius_m'         => (int) $task['unlock_radius_m'],
                        'options'                 => $optionsByTask[(int) $task['id']] ?? [],
                    ],
                ];
            }, $waypoints),
        ];
    }

    /** "2026-07-01 00:00:00" -> "2026-07-01T00:00" (format pola datetime-local). */
    private static function localDate(?string $value): string
    {
        return $value ? str_replace(' ', 'T', substr($value, 0, 16)) : '';
    }
}
