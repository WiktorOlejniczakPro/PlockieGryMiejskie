<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Odczyty rozgrywek graczy: historia gracza i rankingi. */
final class GameSession
{
    /** Historia rozgrywek gracza (najnowsze pierwsze). */
    public static function historyForPlayer(int $playerId): array
    {
        $rows = Database::query(
            'SELECT s.id, s.game_id, s.status, s.score, s.started_at, s.finished_at, g.title, g.is_sample,
                    g.status AS game_status,
                    (SELECT COUNT(*) FROM waypoints w WHERE w.game_id = s.game_id) AS total_waypoints,
                    (SELECT COUNT(*) FROM waypoints w
                      WHERE w.game_id = s.game_id
                        AND (EXISTS (SELECT 1 FROM session_waypoints sw WHERE sw.session_id = s.id AND sw.waypoint_id = w.id)
                             OR EXISTS (SELECT 1 FROM tasks t JOIN task_submissions ts ON ts.task_id = t.id
                                         WHERE t.waypoint_id = w.id AND ts.session_id = s.id))) AS completed_waypoints
               FROM game_sessions s JOIN games g ON g.id = s.game_id
              WHERE s.player_id = ?
              ORDER BY s.started_at DESC, s.id DESC
              LIMIT 100',
            [$playerId]
        )->fetchAll();

        return array_map(static fn (array $r): array => [
            'session_id'  => (int) $r['id'],
            'game_id'     => (int) $r['game_id'],
            'game_title'  => Game::displayTitle($r),
            'status'      => $r['status'],
            'score'       => (int) $r['score'],
            'started_at'  => $r['started_at'],
            'finished_at' => $r['finished_at'],
            'completed_waypoints' => (int) $r['completed_waypoints'],
            'total_waypoints'   => (int) $r['total_waypoints'],
            // gra ukryta / zarchiwizowana przez administratora – historia zostaje
            'game_available'    => $r['game_status'] === 'published',
        ], $rows);
    }

    /** Ranking gry: najlepszy UKOŃCZONY wynik każdego gracza; remis – kto szybciej ukończył. */
    public static function gameRanking(int $gameId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $rows = Database::query(
            "SELECT p.id AS player_id, p.nickname, best.score, best.finished_at
               FROM (
                     SELECT s.player_id, s.score, s.finished_at,
                            ROW_NUMBER() OVER (PARTITION BY s.player_id ORDER BY s.score DESC, s.finished_at ASC) AS rn
                       FROM game_sessions s
                      WHERE s.game_id = ? AND s.status = 'completed'
                    ) best
               JOIN players p ON p.id = best.player_id
              WHERE best.rn = 1
              ORDER BY best.score DESC, best.finished_at ASC
              LIMIT {$limit}",
            [$gameId]
        )->fetchAll();
        return self::ranked($rows);
    }

    /** Ranking ogólny: suma najlepszych wyników gracza ze wszystkich ukończonych gier. */
    public static function overallRanking(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $rows = Database::query(
            "SELECT p.id AS player_id, p.nickname, SUM(best.score) AS score, COUNT(*) AS games_completed
               FROM (
                     SELECT player_id, game_id, MAX(score) AS score
                       FROM game_sessions WHERE status = 'completed'
                      GROUP BY player_id, game_id
                    ) best
               JOIN players p ON p.id = best.player_id
              GROUP BY p.id, p.nickname
              ORDER BY score DESC, games_completed DESC, p.id ASC
              LIMIT {$limit}"
        )->fetchAll();
        return self::ranked($rows);
    }

    private static function ranked(array $rows): array
    {
        $out = [];
        foreach ($rows as $i => $r) {
            $entry = [
                'rank'      => $i + 1,
                'player_id' => (int) $r['player_id'],
                'nickname'  => $r['nickname'],
                'score'     => (int) $r['score'],
            ];
            if (isset($r['games_completed'])) {
                $entry['games_completed'] = (int) $r['games_completed'];
            }
            if (isset($r['finished_at'])) {
                $entry['finished_at'] = $r['finished_at'];
            }
            $out[] = $entry;
        }
        return $out;
    }
}
