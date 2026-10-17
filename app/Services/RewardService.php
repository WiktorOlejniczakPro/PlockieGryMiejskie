<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Models\Reward;
use PDO;

/**
 * Sklep punktów w aplikacji: saldo gracza, nagrody z Kreatora nagród i wymiany.
 *
 * Saldo NIE jest przechowywane – zawsze liczone z bazy:
 *   suma najlepszych wyników z każdej gry (ponowne przejście tej samej gry
 *   nie dodaje punktów drugi raz) − koszt wymian (bez anulowanych).
 * Wymiana jest atomowa: blokada wiersza gracza, nagrody i wolnego kodu z puli,
 * sprawdzenie salda i limitu na gracza, zapis – w jednej transakcji.
 * Ten sam idempotency_key od aplikacji zwraca tę samą wymianę (ponowione
 * żądanie nie pobiera punktów 2×). Kody z puli widzi tylko gracz, który je odebrał.
 */
final class RewardService
{
    /** Nagroda widoczna w aplikacji: aktywna i w terminie ważności. */
    private const VISIBLE = 'r.is_active = 1
        AND (r.valid_from IS NULL OR r.valid_from <= CURDATE())
        AND (r.valid_until IS NULL OR r.valid_until >= CURDATE())';

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

    /** Nagrody z Kreatora z informacją o liczbie kodów, limicie gracza i czy go na nie stać. */
    public static function listForPlayer(int $playerId): array
    {
        $balance = self::balance($playerId)['balance'];
        $rows = Database::query(
            "SELECT r.*,
                    (SELECT COUNT(*) FROM reward_codes c WHERE c.reward_id = r.id AND c.status = 'available') AS codes_available,
                    (SELECT COUNT(*) FROM reward_redemptions x
                      WHERE x.reward_id = r.id AND x.player_id = ? AND x.status <> 'cancelled') AS player_taken
               FROM rewards r
              WHERE " . self::VISIBLE . '
              ORDER BY r.cost_points, r.id',
            [$playerId]
        )->fetchAll();

        return array_map(static function (array $r) use ($balance): array {
            $left = (int) $r['codes_available'];
            $limitLeft = max(0, (int) $r['per_player_limit'] - (int) $r['player_taken']);
            return [
                'id'                => (int) $r['id'],
                'title'             => $r['title'],
                'description'       => $r['description'],
                'reward_type'       => $r['reward_type'],
                'value_label'       => Reward::valueLabel($r),
                'partner_name'      => $r['partner_name'],
                'partner_url'       => $r['partner_url'],
                'min_order_amount'  => $r['min_order_amount'] !== null ? (float) $r['min_order_amount'] : null,
                'terms'             => $r['terms'],
                'cost_points'       => (int) $r['cost_points'],
                'stock_left'        => $left,
                'per_player_limit'  => (int) $r['per_player_limit'],
                'player_limit_left' => $limitLeft,
                'valid_until'       => $r['valid_until'],
                'is_sample'         => (bool) $r['is_sample'],
                'available'         => $left > 0 && $limitLeft > 0,
                'affordable'        => $balance >= (int) $r['cost_points'],
            ];
        }, $rows);
    }

    /** Wymiana punktów na nagrodę – gracz dostaje jeden wolny kod z puli. */
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

            $stmt = $pdo->prepare('SELECT * FROM rewards r WHERE r.id = ? AND ' . self::VISIBLE . ' FOR UPDATE');
            $stmt->execute([$rewardId]);
            $reward = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$reward) {
                throw new HttpException(404, 'Ta nagroda nie jest już dostępna.');
            }

            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM reward_redemptions WHERE reward_id = ? AND player_id = ? AND status <> 'cancelled'"
            );
            $stmt->execute([$rewardId, $playerId]);
            if ((int) $stmt->fetchColumn() >= (int) $reward['per_player_limit']) {
                throw new HttpException(409, (int) $reward['per_player_limit'] === 1
                    ? 'Tę nagrodę możesz odebrać tylko raz.'
                    : sprintf('Tę nagrodę możesz odebrać najwyżej %d razy.', (int) $reward['per_player_limit']));
            }

            $cost = (int) $reward['cost_points'];
            $balance = self::balance($playerId, $pdo)['balance'];
            if ($balance < $cost) {
                throw new HttpException(422, sprintf('Masz za mało punktów: %d z %d potrzebnych.', $balance, $cost));
            }

            $stmt = $pdo->prepare(
                "SELECT id FROM reward_codes WHERE reward_id = ? AND status = 'available' ORDER BY id LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([$rewardId]);
            $codeId = $stmt->fetchColumn();
            if ($codeId === false) {
                throw new HttpException(409, 'Kody do tej nagrody właśnie się skończyły.');
            }

            $pdo->prepare("UPDATE reward_codes SET status = 'redeemed', redeemed_at = NOW() WHERE id = ?")
                ->execute([(int) $codeId]);
            $pdo->prepare(
                'INSERT INTO reward_redemptions (reward_id, reward_code_id, player_id, cost_points, idempotency_key)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([$rewardId, (int) $codeId, $playerId, $cost, $idempotencyKey]);
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
            'SELECT x.*, c.code, r.title, r.reward_type, r.discount_kind, r.value_amount, r.partner_name, r.partner_url,
                    r.terms, r.min_order_amount, r.valid_until, r.is_sample
               FROM reward_redemptions x
               JOIN rewards r ON r.id = x.reward_id
               JOIN reward_codes c ON c.id = x.reward_code_id
              WHERE x.id = ? AND x.player_id = ?',
            [$id, $playerId]
        )->fetch();
        if (!$r) {
            throw new HttpException(404, 'Nie znaleziono wymiany.');
        }
        return [
            'id'                      => (int) $r['id'],
            'reward_id'               => (int) $r['reward_id'],
            'title'                   => $r['title'],
            'reward_type'             => $r['reward_type'],
            'value_label'             => Reward::valueLabel($r),
            'partner_name'            => $r['partner_name'],
            'partner_url'             => $r['partner_url'],
            'cost_points'             => (int) $r['cost_points'],
            'code'                    => $r['code'],
            'status'                  => $r['status'],
            // warunki z Kreatora nagród (i minimalna wartość zakupów) – jak skorzystać z kodu
            'redemption_instructions' => self::instructions($r),
            'valid_until'             => $r['valid_until'],
            'is_sample'               => (bool) $r['is_sample'],
            'created_at'              => $r['created_at'],
            'used_at'                 => $r['used_at'],
        ];
    }

    private static function instructions(array $r): ?string
    {
        $parts = [];
        if ($r['min_order_amount'] !== null) {
            $parts[] = 'Minimalna wartość zakupów: ' . Reward::money((float) $r['min_order_amount']) . ' zł.';
        }
        if (!empty($r['terms'])) {
            $parts[] = (string) $r['terms'];
        }
        return $parts ? implode(' ', $parts) : null;
    }
}
