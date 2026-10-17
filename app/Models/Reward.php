<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Nagrody wymieniane przez graczy na punkty (tabele rewards i reward_codes).
 * Panel przygotowuje katalog i pule kodów; gracze wymieniają punkty w aplikacji
 * (RewardService) – każdy dostaje jeden wolny kod z puli.
 */
final class Reward
{
    private const COLUMNS = ['reward_type', 'title', 'partner_name', 'partner_url', 'description', 'discount_kind',
        'value_amount', 'min_order_amount', 'cost_points', 'per_player_limit', 'terms', 'valid_from',
        'valid_until', 'is_active'];

    /** Wszystkie nagrody z liczbą dostępnych i wydanych kodów. */
    public static function all(): array
    {
        return Database::query(
            "SELECT r.*,
                    (SELECT COUNT(*) FROM reward_codes c WHERE c.reward_id = r.id AND c.status = 'available') AS codes_available,
                    (SELECT COUNT(*) FROM reward_codes c WHERE c.reward_id = r.id AND c.status = 'redeemed')  AS codes_redeemed
               FROM rewards r
              ORDER BY r.is_active DESC, r.cost_points ASC, r.id DESC"
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $row = Database::query(
            "SELECT r.*,
                    (SELECT COUNT(*) FROM reward_codes c WHERE c.reward_id = r.id AND c.status = 'available') AS codes_available,
                    (SELECT COUNT(*) FROM reward_codes c WHERE c.reward_id = r.id AND c.status = 'redeemed')  AS codes_redeemed
               FROM rewards r WHERE r.id = ?",
            [$id]
        )->fetch();
        return $row ?: null;
    }

    /** Podsumowanie do nagłówka listy. */
    public static function stats(): array
    {
        $row = Database::query(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(is_active = 1), 0) AS active,
                    (SELECT COUNT(*) FROM reward_codes WHERE status = 'available') AS codes_available
               FROM rewards"
        )->fetch();
        return array_map('intval', $row ?: ['total' => 0, 'active' => 0, 'codes_available' => 0]);
    }

    /** @param array $data dane po RewardValidationService */
    public static function create(array $data, ?int $userId): int
    {
        $cols = self::COLUMNS;
        $sql = 'INSERT INTO rewards (created_by, ' . implode(', ', $cols) . ') VALUES (?' . str_repeat(', ?', count($cols)) . ')';
        Database::query($sql, array_merge([$userId], array_map(static fn ($c) => $data[$c], $cols)));
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $sets = implode(', ', array_map(static fn ($c) => "{$c} = ?", self::COLUMNS));
        Database::query("UPDATE rewards SET {$sets} WHERE id = ?",
            array_merge(array_map(static fn ($c) => $data[$c], self::COLUMNS), [$id]));
    }

    public static function setActive(int $id, bool $active): void
    {
        Database::query('UPDATE rewards SET is_active = ? WHERE id = ?', [$active ? 1 : 0, $id]);
    }

    public static function delete(int $id): void
    {
        Database::query('DELETE FROM rewards WHERE id = ?', [$id]);
    }

    /** Kody nagrody: najpierw dostępne, potem wydane. */
    public static function codes(int $rewardId): array
    {
        return Database::query(
            "SELECT c.id, c.code, c.status, c.redeemed_at, c.created_at, p.nickname AS player_nickname
               FROM reward_codes c
               LEFT JOIN reward_redemptions x ON x.reward_code_id = c.id
               LEFT JOIN players p ON p.id = x.player_id
              WHERE c.reward_id = ? ORDER BY c.status = 'redeemed', c.id",
            [$rewardId]
        )->fetchAll();
    }

    /**
     * Dodaje kody do puli; duplikaty (już istniejące w tej nagrodzie) są pomijane.
     * @param list<string> $codes
     * @return int liczba faktycznie dodanych kodów
     */
    public static function addCodes(int $rewardId, array $codes): int
    {
        return Database::transaction(static function (\PDO $pdo) use ($rewardId, $codes): int {
            $stmt = $pdo->prepare('INSERT IGNORE INTO reward_codes (reward_id, code) VALUES (?, ?)');
            $added = 0;
            foreach ($codes as $code) {
                $stmt->execute([$rewardId, $code]);
                $added += $stmt->rowCount();
            }
            return $added;
        });
    }

    /** Usuwa kod tylko wtedy, gdy nie został jeszcze wydany graczowi. */
    public static function deleteAvailableCode(int $rewardId, int $codeId): bool
    {
        return Database::query(
            "DELETE FROM reward_codes WHERE id = ? AND reward_id = ? AND status = 'available'",
            [$codeId, $rewardId]
        )->rowCount() === 1;
    }

    /** Wartość nagrody do wyświetlenia: „−10%”, „−15 zł”, „50 zł”. */
    public static function valueLabel(array $r): string
    {
        $amount = self::money((float) $r['value_amount']);
        if ($r['reward_type'] === 'gift_card') {
            return $amount . ' zł';
        }
        return $r['discount_kind'] === 'percent'
            ? '−' . self::money((float) $r['value_amount']) . '%'
            : '−' . $amount . ' zł';
    }

    /** 15.00 -> "15", 12.50 -> "12,50", 1500 -> "1 500". */
    public static function money(float $value): string
    {
        $decimals = fmod($value, 1.0) === 0.0 ? 0 : 2;
        return number_format($value, $decimals, ',', ' ');
    }

    /**
     * Kod częściowo ukryty – widać najwyżej 1/3 znaków:
     * „DEMO-KSIAZKI-0001” -> „DE•••••••••••••01”, „PIZZA-001” -> „P•••••••1”.
     */
    public static function maskCode(string $code): string
    {
        $len = mb_strlen($code);
        if ($len <= 4) {
            return str_repeat('•', $len);
        }
        $edge = max(1, min(4, intdiv($len, 6)));
        return mb_substr($code, 0, $edge) . str_repeat('•', $len - 2 * $edge) . mb_substr($code, -$edge);
    }
}
