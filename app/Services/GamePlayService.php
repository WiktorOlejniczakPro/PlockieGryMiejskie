<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use PDO;
use PDOException;

/**
 * Rozgrywka w aplikacji mobilnej – CAŁA logika jest po stronie serwera,
 * aplikacja tylko pokazuje stan i wysyła zgłoszenia:
 *
 *  - dotarcie do punktu: serwer liczy odległość od zgłoszonej pozycji GPS
 *    i przyjmuje ją, gdy gracz jest w promieniu punktu (+ tolerancja GPS),
 *  - punkty przechodzi się PO KOLEI (kolejność z edytora – waypoints.position):
 *    dotarcie i zadanie są możliwe tylko dla bieżącego punktu, czyli
 *    pierwszego nieukończonego; dostępność zadania wg unlock_mode:
 *      always / after_previous – gdy punkt jest bieżący,
 *      on_arrival              – gdy gracz dotarł do bieżącego punktu,
 *  - limit rozpoczętych gier na dzień (Europe/Warsaw, domyślnie 2); wznowienie
 *    trwającej rozgrywki nie zużywa limitu,
 *  - ocena odpowiedzi (jedna próba na zadanie):
 *      pytania zamknięte – automatycznie (wszystko albo nic),
 *      odpowiedź otwarta – porównanie z expected_answer (bez wielkości liter
 *        i polskich znaków; kilka wariantów oddzielonych średnikiem lub
 *        nową linią); bez wzorca – zaliczona jako "unverified",
 *      zdjęcie / film  – zaliczone po przesłaniu pliku ("unverified"),
 *      info            – potwierdzenie przeczytania, 0 punktów,
 *  - punkt jest ukończony, gdy gracz do niego dotarł (punkt bez zadania)
 *    albo przesłał odpowiedź na zadanie; po ukończeniu wszystkich punktów
 *    rozgrywka kończy się i gracz dostaje completion_points gry.
 */
final class GamePlayService
{
    /** Promień dotarcia dla punktu bez zadania. */
    public const DEFAULT_RADIUS_M = 50;

    /**
     * Limit gier na dziś (strefa Europe/Warsaw – granice dnia liczy PHP, nie zegar telefonu).
     * @return array{daily_limit:int, used_today:int, remaining:int, resets_at:string}
     */
    public static function dailyLimit(int $playerId): array
    {
        $limit = (int) config('app.player.daily_game_limit', 2);
        [$from, $to] = self::todayRange();
        $used = (int) Database::query(
            'SELECT COUNT(*) FROM game_sessions WHERE player_id = ? AND started_at >= ? AND started_at < ?',
            [$playerId, $from, $to]
        )->fetchColumn();
        return [
            'daily_limit' => $limit,
            'used_today'  => $used,
            'remaining'   => max(0, $limit - $used),
            'resets_at'   => $to,
        ];
    }

    /** @return array{0:string, 1:string} początek dzisiejszego i jutrzejszego dnia w Warszawie */
    private static function todayRange(): array
    {
        $tz = new \DateTimeZone('Europe/Warsaw');
        $today = new \DateTimeImmutable('today', $tz);
        return [$today->format('Y-m-d H:i:s'), $today->modify('+1 day')->format('Y-m-d H:i:s')];
    }

    // ------------------------------------------------------------------
    //  Operacje
    // ------------------------------------------------------------------

    /** Rozpoczyna grę albo zwraca rozgrywkę, która już trwa. */
    public function start(int $gameId, int $playerId): array
    {
        $this->visibleGame($gameId);

        $sessionId = Database::transaction(function (PDO $pdo) use ($gameId, $playerId): int {
            // blokada wiersza gracza – dwa szybkie kliknięcia nie utworzą dwóch rozgrywek
            $pdo->prepare('SELECT id FROM players WHERE id = ? FOR UPDATE')->execute([$playerId]);
            $stmt = $pdo->prepare("SELECT id FROM game_sessions WHERE game_id = ? AND player_id = ? AND status = 'in_progress'");
            $stmt->execute([$gameId, $playerId]);
            $existing = $stmt->fetchColumn();
            if ($existing !== false) {
                return (int) $existing; // wznowienie – nie liczy się do limitu
            }

            // limit sprawdzany w tej samej transakcji co zapis (wiersz gracza zablokowany wyżej),
            // więc dwa równoczesne żądania nie przekroczą limitu
            $limit = self::dailyLimit($playerId);
            if ($limit['remaining'] <= 0) {
                throw new HttpException(429, sprintf(
                    'Wykorzystałeś dzisiejszy limit gier (%d). Kolejną grę rozpoczniesz jutro.', $limit['daily_limit']
                ));
            }

            $version = (int) $pdo->query('SELECT lock_version FROM games WHERE id = ' . $gameId)->fetchColumn();
            $pdo->prepare('INSERT INTO game_sessions (game_id, player_id, game_version, started_at) VALUES (?, ?, ?, ?)')
                ->execute([$gameId, $playerId, $version, date('Y-m-d H:i:s')]);
            return (int) $pdo->lastInsertId();
        });

        return $this->state($sessionId, $playerId);
    }

    /** Stan rozgrywki gracza (404, gdy rozgrywka nie istnieje lub należy do kogoś innego). */
    public function state(int $sessionId, int $playerId): array
    {
        $session = $this->session($sessionId, $playerId);
        return $this->buildState($session, $this->gameData((int) $session['game_id']));
    }

    /** Zgłoszenie dotarcia do punktu z pozycją GPS. */
    public function arrive(int $sessionId, int $playerId, int $waypointId, float $lat, float $lng, ?int $accuracy): array
    {
        $session = $this->activeSession($sessionId, $playerId);
        $game = $this->gameData((int) $session['game_id']);
        $wp = $game['waypoints'][$waypointId] ?? throw new HttpException(404, 'Ten punkt nie należy do tej gry.');

        // trasa po kolei: można zgłosić tylko bieżący punkt (ponowne zgłoszenie dotartego – bez błędu)
        $state = $this->buildState($session, $game);
        $wpState = null;
        foreach ($state['waypoints'] as $w) {
            if ($w['waypoint_id'] === $waypointId) {
                $wpState = $w;
            }
        }
        if ($wpState !== null && !$wpState['reached'] && $state['next_waypoint_id'] !== $waypointId) {
            $next = $game['waypoints'][$state['next_waypoint_id']] ?? null;
            throw new HttpException(409, $next
                ? sprintf('Najpierw dotrzyj do punktu %d: „%s”.', $next['position'], $next['name'])
                : 'Ten punkt nie jest teraz aktywny.');
        }

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new HttpException(422, 'Nieprawidłowe współrzędne.');
        }
        $distance = Geo::distanceMeters($lat, $lng, (float) $wp['latitude'], (float) $wp['longitude']);
        $tolerance = min(max($accuracy ?? 0, 0), (int) config('app.player.gps_tolerance_max_m', 30));
        $allowed = $wp['radius'] + $tolerance;
        if ($distance > $allowed) {
            throw new HttpException(422, sprintf(
                'Jesteś jeszcze za daleko od punktu „%s” (ok. %d m, potrzeba najwyżej %d m).',
                $wp['name'], (int) round($distance), $allowed
            ));
        }

        Database::query(
            'INSERT IGNORE INTO session_waypoints (session_id, waypoint_id, latitude, longitude, accuracy_m, distance_m)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$sessionId, $waypointId, $lat, $lng, $accuracy !== null ? min(max($accuracy, 0), 65535) : null,
             min((int) round($distance), 65535)]
        );
        return $this->refresh($sessionId, $playerId, $game);
    }

    /**
     * Odpowiedź na zadanie.
     * @param array $input answer_text (string) / option_ids (lista id opcji)
     * @param array|null $file plik z $_FILES (zadania photo / video)
     */
    public function answer(int $sessionId, int $playerId, int $taskId, array $input, ?array $file): array
    {
        $session = $this->activeSession($sessionId, $playerId);
        $game = $this->gameData((int) $session['game_id']);
        $task = $game['tasks'][$taskId] ?? throw new HttpException(404, 'To zadanie nie należy do tej gry.');

        $state = $this->buildState($session, $game);
        $wpState = $state['waypoints'][$task['waypoint_index']];
        if ($wpState['task']['submission'] !== null) {
            throw new HttpException(409, 'To zadanie zostało już wykonane.');
        }
        if (!$wpState['task']['available']) {
            throw new HttpException(403, $state['next_waypoint_id'] !== $wpState['waypoint_id']
                ? 'Najpierw ukończ poprzednie punkty trasy.'
                : 'Zadanie odblokuje się, gdy dotrzesz do punktu.');
        }

        $result = $this->evaluate($task, $input, $file);

        $stored = null;
        if (in_array($task['task_type'], ['photo', 'video'], true)) {
            $stored = (new SubmissionUploadService())->store($file, $task['task_type']);
        }

        try {
            Database::transaction(function (PDO $pdo) use ($sessionId, $taskId, $result, $stored): void {
                $pdo->prepare(
                    'INSERT INTO task_submissions (session_id, task_id, answer_text, selected_option_ids, is_correct,
                                                   points_awarded, review_status)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $sessionId, $taskId, $result['answer_text'],
                    $result['option_ids'] !== null ? json_encode($result['option_ids']) : null,
                    $result['is_correct'] === null ? null : (int) $result['is_correct'],
                    $result['points'], $result['review_status'],
                ]);
                if ($stored !== null) {
                    $pdo->prepare(
                        'INSERT INTO submission_media (submission_id, storage_path, mime_type, file_size, sha256)
                         VALUES (?, ?, ?, ?, ?)'
                    )->execute([(int) $pdo->lastInsertId(), $stored['path'], $stored['mime'], $stored['size'], $stored['sha256']]);
                }
            });
        } catch (PDOException $e) {
            if ($stored !== null) {
                SubmissionUploadService::delete($stored['path']);
            }
            if ($e->getCode() === '23000') {
                throw new HttpException(409, 'To zadanie zostało już wykonane.');
            }
            throw $e;
        }

        return $this->refresh($sessionId, $playerId, $game);
    }

    // ------------------------------------------------------------------
    //  Ocena odpowiedzi
    // ------------------------------------------------------------------

    /** @return array{answer_text:?string, option_ids:?list<int>, is_correct:?bool, points:int, review_status:string} */
    private function evaluate(array $task, array $input, ?array $file): array
    {
        $max = (int) $task['max_points'];
        $out = ['answer_text' => null, 'option_ids' => null, 'is_correct' => null, 'points' => 0, 'review_status' => 'auto'];

        switch ($task['task_type']) {
            case 'single_choice':
            case 'multiple_choice':
                $ids = array_values(array_unique(array_map('intval', array_filter(
                    (array) ($input['option_ids'] ?? []), 'is_numeric'
                ))));
                $valid = array_keys($task['options']);
                if (!$ids || array_diff($ids, $valid)) {
                    throw new HttpException(422, 'Wybierz odpowiedź spośród dostępnych opcji.');
                }
                if ($task['task_type'] === 'single_choice' && count($ids) !== 1) {
                    throw new HttpException(422, 'W tym pytaniu wybierz dokładnie jedną odpowiedź.');
                }
                $correct = array_keys(array_filter($task['options']));
                sort($ids);
                sort($correct);
                $out['option_ids'] = $ids;
                $out['is_correct'] = $ids === $correct;
                $out['points'] = $out['is_correct'] ? $max : 0;
                return $out;

            case 'open_answer':
                $answer = trim((string) ($input['answer_text'] ?? ''));
                if ($answer === '' || mb_strlen($answer) > 2000) {
                    throw new HttpException(422, 'Wpisz odpowiedź (najwyżej 2000 znaków).');
                }
                $out['answer_text'] = $answer;
                $expected = array_filter(array_map([self::class, 'normalize'],
                    preg_split('/[;\n]/u', (string) ($task['expected_answer'] ?? ''))), fn ($v) => $v !== '');
                if (!$expected) {
                    $out['review_status'] = 'unverified';
                    $out['points'] = $max;
                    return $out;
                }
                $out['is_correct'] = in_array(self::normalize($answer), $expected, true);
                $out['points'] = $out['is_correct'] ? $max : 0;
                return $out;

            case 'photo':
            case 'video':
                if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    throw new HttpException(422, $task['task_type'] === 'photo' ? 'Dołącz zdjęcie.' : 'Dołącz nagranie.');
                }
                $comment = trim((string) ($input['answer_text'] ?? ''));
                $out['answer_text'] = $comment !== '' ? mb_substr($comment, 0, 2000) : null;
                $out['review_status'] = 'unverified';
                $out['points'] = $max;
                return $out;

            default: // info
                return $out;
        }
    }

    /** "  Bazylika  KATEDRALNA! " -> "bazylika katedralna" (bez polskich znaków i interpunkcji). */
    public static function normalize(string $text): string
    {
        $text = strtr(mb_strtolower(trim($text)), [
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
        ]);
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);
        return trim($text);
    }

    // ------------------------------------------------------------------
    //  Stan rozgrywki
    // ------------------------------------------------------------------

    /** Po zmianie: przelicza wynik, ewentualnie kończy grę i zwraca nowy stan. */
    private function refresh(int $sessionId, int $playerId, array $game): array
    {
        $session = $this->session($sessionId, $playerId);
        $state = $this->buildState($session, $game);

        $finished = $state['completed_waypoints'] === $state['total_waypoints'] && $state['total_waypoints'] > 0;
        $score = $state['task_points'] + ($finished ? $state['completion_points'] : 0);
        Database::query(
            "UPDATE game_sessions
                SET score = ?, status = IF(?, 'completed', status), finished_at = IF(? AND finished_at IS NULL, NOW(), finished_at)
              WHERE id = ? AND status = 'in_progress'",
            [$score, (int) $finished, (int) $finished, $sessionId]
        );

        return $this->buildState($this->session($sessionId, $playerId), $game);
    }

    private function buildState(array $session, array $game): array
    {
        $sessionId = (int) $session['id'];
        $reached = Database::query(
            'SELECT waypoint_id, reached_at FROM session_waypoints WHERE session_id = ?', [$sessionId]
        )->fetchAll(PDO::FETCH_KEY_PAIR);
        $submissions = [];
        foreach (Database::query(
            'SELECT task_id, is_correct, points_awarded, review_status, selected_option_ids, submitted_at
               FROM task_submissions WHERE session_id = ?', [$sessionId]
        )->fetchAll() as $row) {
            $submissions[(int) $row['task_id']] = $row;
        }

        $waypoints = [];
        $previousCompleted = true; // dla pierwszego punktu
        $completed = 0;
        $taskPoints = 0;
        $next = null;
        foreach ($game['waypoints'] as $wp) {
            $isReached = isset($reached[$wp['id']]);
            $task = $wp['task_id'] !== null ? $game['tasks'][$wp['task_id']] : null;
            $submission = $task !== null ? ($submissions[$task['id']] ?? null) : null;

            $taskState = null;
            if ($task !== null) {
                // bieżący punkt = wszystkie wcześniejsze ukończone
                $available = $previousCompleted && ($task['unlock_mode'] !== 'on_arrival' || $isReached);
                $taskState = [
                    'task_id'    => $task['id'],
                    'available'  => $available,
                    'submission' => $submission === null ? null : [
                        'is_correct'     => $submission['is_correct'] === null ? null : (bool) $submission['is_correct'],
                        'points_awarded' => (int) $submission['points_awarded'],
                        'max_points'     => (int) $task['max_points'],
                        'review_status'  => $submission['review_status'],
                        'submitted_at'   => $submission['submitted_at'],
                        // po odpowiedzi gracz może zobaczyć, które opcje były poprawne
                        'correct_option_ids' => in_array($task['task_type'], ['single_choice', 'multiple_choice'], true)
                            ? array_keys(array_filter($task['options'])) : null,
                        'selected_option_ids' => $submission['selected_option_ids'] !== null
                            ? json_decode((string) $submission['selected_option_ids'], true) : null,
                    ],
                ];
                if ($submission !== null && $submission['review_status'] !== 'rejected') {
                    $taskPoints += (int) $submission['points_awarded'];
                }
            }

            $isCompleted = $task === null ? $isReached : $submission !== null;
            $completed += (int) $isCompleted;
            if (!$isCompleted && $next === null) {
                $next = $wp['id'];
            }
            $previousCompleted = $previousCompleted && $isCompleted;

            $waypoints[] = [
                'waypoint_id' => $wp['id'],
                'position'    => $wp['position'],
                'reached'     => $isReached,
                'reached_at'  => $reached[$wp['id']] ?? null,
                'completed'   => $isCompleted,
                'task'        => $taskState,
            ];
        }

        return [
            'id'                  => $sessionId,
            'game_id'             => (int) $session['game_id'],
            'status'              => $session['status'],
            'score'               => (int) $session['score'],
            'task_points'         => $taskPoints,
            'completion_points'   => $game['completion_points'],
            'max_score'           => $game['max_score'],
            'completed_waypoints' => $completed,
            'total_waypoints'     => count($game['waypoints']),
            'next_waypoint_id'    => $next,
            'game_version'        => (int) ($session['game_version'] ?? 1),
            'started_at'          => $session['started_at'],
            'finished_at'         => $session['finished_at'],
            'waypoints'           => $waypoints,
        ];
    }

    // ------------------------------------------------------------------
    //  Dane
    // ------------------------------------------------------------------

    private function session(int $sessionId, int $playerId): array
    {
        $row = Database::query('SELECT * FROM game_sessions WHERE id = ? AND player_id = ?', [$sessionId, $playerId])->fetch();
        if (!$row) {
            throw new HttpException(404, 'Nie znaleziono rozgrywki.');
        }
        return $row;
    }

    /** Rozgrywka, w której można jeszcze wykonywać akcje (trwa, gra nadal opublikowana). */
    private function activeSession(int $sessionId, int $playerId): array
    {
        $session = $this->session($sessionId, $playerId);
        if ($session['status'] !== 'in_progress') {
            throw new HttpException(409, 'Ta rozgrywka jest już zakończona.');
        }
        $this->visibleGame((int) $session['game_id']);
        return $session;
    }

    private function visibleGame(int $gameId): void
    {
        $visible = Database::query('SELECT 1 FROM games g WHERE g.id = ? AND ' . PlayerGameSerializer::VISIBLE, [$gameId])->fetchColumn();
        if (!$visible) {
            throw new HttpException(404, 'Ta gra nie jest dostępna.');
        }
    }

    /**
     * Gra z poprawnymi odpowiedziami – TYLKO do użytku wewnętrznego serwera.
     * @return array{completion_points:int, max_score:int, waypoints: array<int, array>, tasks: array<int, array>}
     */
    private function gameData(int $gameId): array
    {
        $completion = (int) Database::query('SELECT completion_points FROM games WHERE id = ?', [$gameId])->fetchColumn();

        $waypoints = [];
        $tasks = [];
        $index = 0;
        foreach (Database::query(
            'SELECT w.id, w.name, w.latitude, w.longitude, w.position,
                    t.id AS task_id, t.task_type, t.max_points, t.expected_answer, t.unlock_mode, t.unlock_radius_m
               FROM waypoints w LEFT JOIN tasks t ON t.waypoint_id = w.id
              WHERE w.game_id = ? ORDER BY w.position',
            [$gameId]
        )->fetchAll() as $row) {
            $id = (int) $row['id'];
            $taskId = $row['task_id'] !== null ? (int) $row['task_id'] : null;
            $waypoints[$id] = [
                'id'        => $id,
                'name'      => $row['name'],
                'latitude'  => (float) $row['latitude'],
                'longitude' => (float) $row['longitude'],
                'position'  => (int) $row['position'],
                'radius'    => $taskId !== null ? (int) $row['unlock_radius_m'] : self::DEFAULT_RADIUS_M,
                'task_id'   => $taskId,
            ];
            if ($taskId !== null) {
                $tasks[$taskId] = [
                    'id'              => $taskId,
                    'waypoint_id'     => $id,
                    'waypoint_index'  => $index,
                    'task_type'       => $row['task_type'],
                    'max_points'      => (int) $row['max_points'],
                    'expected_answer' => $row['expected_answer'],
                    'unlock_mode'     => $row['unlock_mode'],
                    'options'         => [],
                ];
            }
            $index++;
        }

        if ($tasks) {
            $marks = implode(',', array_fill(0, count($tasks), '?'));
            foreach (Database::query(
                "SELECT id, task_id, is_correct FROM task_options WHERE task_id IN ({$marks}) ORDER BY position",
                array_keys($tasks)
            )->fetchAll() as $o) {
                $tasks[(int) $o['task_id']]['options'][(int) $o['id']] = (bool) $o['is_correct'];
            }
        }

        $maxTasks = array_sum(array_column($tasks, 'max_points'));
        return ['completion_points' => $completion, 'max_score' => $maxTasks + $completion, 'waypoints' => $waypoints, 'tasks' => $tasks];
    }
}
