<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use PDO;

/**
 * Zapis całej gry (dane podstawowe, kategorie, punkty, zadania, odpowiedzi)
 * w JEDNEJ transakcji. Błąd w dowolnym miejscu wycofuje wszystkie zmiany,
 * więc gra nigdy nie zostaje zapisana w połowie.
 *
 * Identyfikatory istniejących punktów są zachowywane (aktualizacja zamiast
 * usuwania i wstawiania), co jest ważne dla przyszłych wyników graczy.
 * Konflikt edycji (ta sama gra zapisana w innym oknie) wykrywa lock_version.
 */
final class GameSaveService
{
    private const POSITION_OFFSET = 10000;

    /**
     * @param array $data dane po GameValidationService::normalize()
     * @return int identyfikator gry
     */
    public function save(?int $gameId, int $ownerId, array $data, ?int $lockVersion, int $userId): int
    {
        return Database::transaction(function (PDO $pdo) use ($gameId, $ownerId, $data, $lockVersion, $userId): int {
            $gameId = $gameId === null
                ? $this->insertGame($pdo, $ownerId, $data, $userId)
                : $this->updateGame($pdo, $gameId, $data, $lockVersion);

            $this->syncCategories($pdo, $gameId, $data['categories']);
            $this->syncWaypoints($pdo, $gameId, $data['waypoints']);

            return $gameId;
        });
    }

    private function insertGame(PDO $pdo, int $ownerId, array $d, int $userId): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO games (owner_id, title, short_description, description, estimated_duration_minutes,
                                difficulty, status, completion_points, custom_category, available_from, available_until)
             VALUES (?, ?, ?, ?, ?, ?, \'draft\', ?, ?, ?, ?)'
        );
        $stmt->execute([
            $ownerId, $d['title'], $d['short_description'], $d['description'], $d['estimated_duration_minutes'],
            $d['difficulty'], $d['completion_points'], $d['custom_category'], $d['available_from'], $d['available_until'],
        ]);
        $id = (int) $pdo->lastInsertId();

        $pdo->prepare("INSERT INTO game_status_history (game_id, changed_by, from_status, to_status) VALUES (?, ?, NULL, 'draft')")
            ->execute([$id, $userId]);

        return $id;
    }

    private function updateGame(PDO $pdo, int $gameId, array $d, ?int $lockVersion): int
    {
        $stmt = $pdo->prepare(
            'UPDATE games
                SET title = ?, short_description = ?, description = ?, estimated_duration_minutes = ?,
                    difficulty = ?, completion_points = ?, custom_category = ?, available_from = ?, available_until = ?,
                    lock_version = lock_version + 1
              WHERE id = ? AND lock_version = ? AND status <> \'archived\''
        );
        $stmt->execute([
            $d['title'], $d['short_description'], $d['description'], $d['estimated_duration_minutes'],
            $d['difficulty'], $d['completion_points'], $d['custom_category'], $d['available_from'], $d['available_until'],
            $gameId, (int) $lockVersion,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new HttpException(409, 'Ta gra została w międzyczasie zmieniona w innym oknie lub przez inną osobę. '
                . 'Skopiuj swoje zmiany, odśwież stronę i wprowadź je ponownie.');
        }
        return $gameId;
    }

    private function syncCategories(PDO $pdo, int $gameId, array $categoryIds): void
    {
        $pdo->prepare('DELETE FROM game_categories WHERE game_id = ?')->execute([$gameId]);
        $insert = $pdo->prepare('INSERT INTO game_categories (game_id, category_id) VALUES (?, ?)');
        foreach ($categoryIds as $categoryId) {
            $insert->execute([$gameId, $categoryId]);
        }
    }

    private function syncWaypoints(PDO $pdo, int $gameId, array $waypoints): void
    {
        // Istniejące punkty tej gry (blokada wierszy na czas transakcji)
        $stmt = $pdo->prepare('SELECT id FROM waypoints WHERE game_id = ? FOR UPDATE');
        $stmt->execute([$gameId]);
        $existing = array_flip(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));

        // Identyfikator spoza tej gry traktujemy jak nowy punkt (ochrona przed podmianą cudzych danych)
        $keep = [];
        foreach ($waypoints as $wp) {
            if ($wp['id'] !== null && isset($existing[$wp['id']])) {
                $keep[$wp['id']] = true;
            }
        }

        // Usunięcie punktów, których nie ma w przesłanych danych (zadania usuwa kaskada)
        $toDelete = array_diff_key($existing, $keep);
        if ($toDelete) {
            $ids = array_keys($toDelete);
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM waypoints WHERE game_id = ? AND id IN ({$marks})")->execute([$gameId, ...$ids]);
        }

        // Kolejność: najpierw przesuwamy stare pozycje, żeby nie naruszyć klucza unikalnego
        $pdo->prepare('UPDATE waypoints SET position = position + ' . self::POSITION_OFFSET . ' WHERE game_id = ?')
            ->execute([$gameId]);

        $update = $pdo->prepare(
            'UPDATE waypoints SET name = ?, description = ?, player_hint = ?, latitude = ?, longitude = ?, position = ?
              WHERE id = ? AND game_id = ?'
        );
        $insert = $pdo->prepare(
            'INSERT INTO waypoints (game_id, name, description, player_hint, latitude, longitude, position)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        foreach (array_values($waypoints) as $i => $wp) {
            $position = $i + 1;
            if ($wp['id'] !== null && isset($keep[$wp['id']])) {
                $update->execute([$wp['name'], $wp['description'], $wp['player_hint'], $wp['latitude'], $wp['longitude'],
                                  $position, $wp['id'], $gameId]);
                $waypointId = $wp['id'];
            } else {
                $insert->execute([$gameId, $wp['name'], $wp['description'], $wp['player_hint'], $wp['latitude'],
                                  $wp['longitude'], $position]);
                $waypointId = (int) $pdo->lastInsertId();
            }
            $this->syncTask($pdo, $waypointId, $wp['task']);
        }
    }

    private function syncTask(PDO $pdo, int $waypointId, ?array $task): void
    {
        if ($task === null) {
            $pdo->prepare('DELETE FROM tasks WHERE waypoint_id = ?')->execute([$waypointId]);
            return;
        }

        $values = [
            $task['title'], $task['description'], $task['task_type'], $task['hint'], $task['max_points'],
            $task['expected_answer'], $task['evaluation_instructions'], $task['media_requirement'],
            $task['media_max_seconds'], $task['unlock_mode'], $task['unlock_radius_m'],
        ];

        $stmt = $pdo->prepare('SELECT id FROM tasks WHERE waypoint_id = ?');
        $stmt->execute([$waypointId]);
        $taskId = $stmt->fetchColumn();

        if ($taskId !== false) {
            $taskId = (int) $taskId;
            $pdo->prepare(
                'UPDATE tasks SET title = ?, description = ?, task_type = ?, hint = ?, max_points = ?,
                        expected_answer = ?, evaluation_instructions = ?, media_requirement = ?,
                        media_max_seconds = ?, unlock_mode = ?, unlock_radius_m = ?
                  WHERE id = ?'
            )->execute([...$values, $taskId]);
        } else {
            $pdo->prepare(
                'INSERT INTO tasks (title, description, task_type, hint, max_points, expected_answer,
                                    evaluation_instructions, media_requirement, media_max_seconds,
                                    unlock_mode, unlock_radius_m, waypoint_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([...$values, $waypointId]);
            $taskId = (int) $pdo->lastInsertId();
        }

        // Odpowiedzi do pytań zamkniętych zapisujemy od nowa w podanej kolejności
        $pdo->prepare('DELETE FROM task_options WHERE task_id = ?')->execute([$taskId]);
        $insert = $pdo->prepare('INSERT INTO task_options (task_id, option_text, is_correct, position) VALUES (?, ?, ?, ?)');
        foreach (array_values($task['options']) as $i => $option) {
            $insert->execute([$taskId, $option['text'], $option['is_correct'] ? 1 : 0, $i + 1]);
        }
    }
}
