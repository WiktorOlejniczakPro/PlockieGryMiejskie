<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use PDO;

/**
 * Sklep punktów: saldo gracza, nagrody i wymiany.
 *
 * Saldo NIE jest przechowywane – zawsze liczone z bazy:
 *   suma najlepszych wyników z każdej gry (ponowne przejście tej samej gry
 *   nie dodaje punktów drugi raz) − koszt wymian (bez anulowanych).
 * Wymiana jest atomowa: blokada wiersza gracza i nagrody, sprawdzenie salda
 * i liczby sztuk, zapis – w jednej transakcji. Ten sam idempotency_key od
 * aplikacji zwraca tę samą wymianę (ponowione żądanie nie pobiera punktów 2×).
 */
final class RewardService
{
    public static function balance(int $playerId, ?PDO $pdo = null): array
    {
        $run = static function (string $sql, array $params) use ($pdo): mixed {
            if ($pdo === null) {
                return Database::query($sql, $params)->fetchColumn();
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn();
        };
        $earned = (int) $run(
            'SELECT COALESCE(SUM(best), 0) FROM (
                 SELECT MAX(score) AS best FROM game_sessions WHERE player_id = ? GROUP BY game_id
             ) t',
            [$playerId]
        );
        $spent = (int) $run(
            "SELECT COALESCE(SUM(cost_points), 0) FROM reward_redemptions WHERE player_id = ? AND status <> 'cancelled'",
            [$playerId]
        );
        return ['earned' => $earned, 'spent' => $spent, 'balance' => max(0, $earned - $spent)];
    }

    /** Aktywne nagrody (w terminie) z informacją, czy gracza na nie stać. */
    public static function listForPlayer(int $playerId): array
    {
        $balance = self::balance($playerId)['balance'];
        $rows = Database::query(
            'SELECT r.*,
                    (SELECT COUNT(*) FROM reward_redemptions x WHERE x.reward_id = r.id AND x.status <> \'cancelled\') AS issued
               FROM rewards r
              WHERE r.is_active = 1 AND (r.valid_until IS NULL OR r.valid_until >= NOW())
              ORDER BY r.cost_points, r.id'
        )->fetchAll();

        return array_map(static function (array $r) use ($balance): array {
            $left = $r['stock'] === null ? null : max(0, (int) $r['stock'] - (int) $r['issued']);
            return [
                'id'               => (int) $r['id'],
                'title'            => self::displayTitle($r),
                'description'      => $r['description'],
                'reward_type'      => $r['reward_type'],
                'partner_name'     => $r['partner_name'],
                'cost_points'      => (int) $r['cost_points'],
                'stock_left'       => $left,
                'valid_until'      => $r['valid_until'],
                'is_sample'        => (bool) $r['is_sample'],
                'available'        => $left === null || $left > 0,
                'affordable'       => $balance >= (int) $r['cost_points'],
            ];
        }, $rows);
    }

    /** Wymiana punktów na nagrodę. */
    public function redeem(int $playerId, int $rewardId, ?string $idempotencyKey): array
    {
        if ($idempotencyKey !== null && !preg_match('/^[A-Za-z0-9-]{8,36}$/', $idempotencyKey)) {
            throw new HttpException(422, 'Nieprawidłowy identyfikator operacji.');
        }

        $redemptionId = Database::transaction(function (PDO $pdo) use ($playerId, $rewardId, $idempotencyKey): int {
            $pdo->prepare('SELECT id FROM players WHERE id = ? FOR UPDATE')->execute([$playerId]);

            if ($idempotencyKey !== null) {
                $stmt = $pdo->prepare('SELECT id FROM reward_redemptions WHERE player_id = ? AND idempotency_key = ?');
                $stmt->execute([$playerId, $idempotencyKey]);
                $existing = $stmt->fetchColumn();
                if ($existing !== false) {
                    return (int) $existing; // ponowione żądanie – ta sama wymiana
                }
            }

            $stmt = $pdo->prepare(
                'SELECT * FROM rewards WHERE id = ? AND is_active = 1 AND (valid_until IS NULL OR valid_until >= NOW()) FOR UPDATE'
            );
            $stmt->execute([$rewardId]);
            $reward = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$reward) {
                throw new HttpException(404, 'Ta nagroda nie jest już dostępna.');
            }

            if ($reward['stock'] !== null) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM reward_redemptions WHERE reward_id = ? AND status <> 'cancelled'");
                $stmt->execute([$rewardId]);
                if ((int) $stmt->fetchColumn() >= (int) $reward['stock']) {
                    throw new HttpException(409, 'Ta nagroda właśnie się skończyła.');
                }
            }

            $cost = (int) $reward['cost_points'];
            $balance = self::balance($playerId, $pdo)['balance'];
            if ($balance < $cost) {
                throw new HttpException(422, sprintf('Masz za mało punktów: %d z %d potrzebnych.', $balance, $cost));
            }

            $pdo->prepare(
                'INSERT INTO reward_redemptions (reward_id, player_id, cost_points, code, idempotency_key) VALUES (?, ?, ?, ?, ?)'
            )->execute([$rewardId, $playerId, $cost, self::newCode(), $idempotencyKey]);
            return (int) $pdo->lastInsertId();
        });

        AuditLog::record('reward.redeemed', null, 'reward_redemption', $redemptionId, ['player_id' => $playerId]);
        return [
            'redemption' => self::redemption($redemptionId, $playerId),
            'balance'    => self::balance($playerId),
        ];
    }

    /** Historia wymian gracza (z kodem – widocznym tylko dla właściciela). */
    public static function redemptionsForPlayer(int $playerId): array
    {
        $rows = Database::query(
            'SELECT x.id FROM reward_redemptions x WHERE x.player_id = ? ORDER BY x.created_at DESC, x.id DESC LIMIT 100',
            [$playerId]
        )->fetchAll(PDO::FETCH_COLUMN);
        return array_map(static fn ($id) => self::redemption((int) $id, $playerId), $rows);
    }

    private static function redemption(int $id, int $playerId): array
    {
        $r = Database::query(
            'SELECT x.*, r.title, r.reward_type, r.partner_name, r.redemption_instructions, r.valid_until, r.is_sample
               FROM reward_redemptions x JOIN rewards r ON r.id = x.reward_id
              WHERE x.id = ? AND x.player_id = ?',
            [$id, $playerId]
        )->fetch();
        if (!$r) {
            throw new HttpException(404, 'Nie znaleziono wymiany.');
        }
        return [
            'id'                      => (int) $r['id'],
            'reward_id'               => (int) $r['reward_id'],
            'title'                   => self::displayTitle($r),
            'reward_type'             => $r['reward_type'],
            'partner_name'            => $r['partner_name'],
            'cost_points'             => (int) $r['cost_points'],
            'code'                    => $r['code'],
            'status'                  => $r['status'],
            'redemption_instructions' => $r['redemption_instructions'],
            'valid_until'             => $r['valid_until'],
            'is_sample'               => (bool) $r['is_sample'],
            'created_at'              => $r['created_at'],
            'used_at'                 => $r['used_at'],
        ];
    }

    /** Usuwa przedrostek „[PRZYKŁAD]” – aplikacja pokazuje etykietę na podstawie is_sample. */
    private static function displayTitle(array $r): string
    {
        return !empty($r['is_sample']) ? (string) preg_replace('/^\[PRZYKŁAD\]\s*/u', '', (string) $r['title']) : (string) $r['title'];
    }

    /** Kod odbioru, np. "PGM-7K3D-Q9XM" (bez mylących znaków 0/O, 1/I). */
    private static function newCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $part = static function () use ($alphabet): string {
            $s = '';
            for ($i = 0; $i < 4; $i++) {
                $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            return $s;
        };
        return 'PGM-' . $part() . '-' . $part();
    }
}
