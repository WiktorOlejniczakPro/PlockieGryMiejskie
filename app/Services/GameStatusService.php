<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Models\Category;
use PDO;

/**
 * Cykl życia gry – JEDNO miejsce, które decyduje, jakie zmiany statusu są dozwolone.
 *
 *   draft      (Szkic)          – gra w przygotowaniu, niewidoczna dla graczy; tylko szkic można edytować.
 *   published  (Opublikowana)   – widoczna na stronie głównej i w aplikacji (w terminie dostępności);
 *                                 zablokowana do edycji – żeby ją zmienić, trzeba ją cofnąć do szkicu.
 *   archived   (Zarchiwizowana) – zakończona, tylko do odczytu; można ją przywrócić jako szkic.
 *
 * Każda zmiana trafia do game_status_history i zwiększa lock_version
 * (otwarty edytor w innym oknie nie nadpisze nowego statusu starymi danymi).
 */
final class GameStatusService
{
    /** Dozwolone przejścia: obecny status => statusy docelowe. */
    public const ALLOWED = [
        'draft'     => ['published', 'archived'],
        'published' => ['draft', 'archived'],
        'archived'  => ['draft'],
    ];

    /**
     * @return list<array> błędy kompletności (pusta lista = OK); przy błędach status się nie zmienia
     */
    public function change(int $gameId, string $to, int $lockVersion, int $userId): array
    {
        $game = Database::query('SELECT status, lock_version FROM games WHERE id = ?', [$gameId])->fetch();
        if (!$game) {
            throw new HttpException(404, 'Nie znaleziono gry.');
        }
        $from = $game['status'];
        if (!in_array($to, self::ALLOWED[$from] ?? [], true)) {
            throw new HttpException(409, sprintf('Nie można zmienić statusu z „%s” na „%s”.', label('status', $from), label('status', $to)));
        }
        if ((int) $game['lock_version'] !== $lockVersion) {
            throw new HttpException(409, 'Gra została w międzyczasie zmieniona w innym oknie. Odśwież stronę i spróbuj ponownie.');
        }

        if ($to === 'published') {
            // walidujemy to, co JEST w bazie (a nie formularz) – publikujemy zapisaną wersję
            $validator = new GameValidationService();
            [$data, $errors] = $validator->normalize(GameSerializer::forEditor($gameId) ?? [], Category::allActive());
            if (!$errors) {
                $errors = $validator->forPublish($data);
            }
            if ($errors) {
                return $errors;
            }
        }

        Database::transaction(function (PDO $pdo) use ($gameId, $from, $to, $lockVersion, $userId): void {
            $stmt = $pdo->prepare(
                "UPDATE games
                    SET status = ?, lock_version = lock_version + 1,
                        published_at = IF(? = 'published' AND published_at IS NULL, NOW(), published_at),
                        archived_at  = IF(? = 'archived', NOW(), NULL)
                  WHERE id = ? AND lock_version = ? AND status = ?"
            );
            $stmt->execute([$to, $to, $to, $gameId, $lockVersion, $from]);
            if ($stmt->rowCount() === 0) {
                throw new HttpException(409, 'Gra została w międzyczasie zmieniona. Odśwież stronę i spróbuj ponownie.');
            }
            $pdo->prepare('INSERT INTO game_status_history (game_id, changed_by, from_status, to_status) VALUES (?, ?, ?, ?)')
                ->execute([$gameId, $userId, $from, $to]);
        });

        AuditLog::record('game.status', $userId, 'game', $gameId, ['from' => $from, 'to' => $to]);
        return [];
    }
}
