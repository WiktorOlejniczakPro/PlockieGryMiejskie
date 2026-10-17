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
 *   draft      (Szkic)          – gra w przygotowaniu, niewidoczna dla graczy.
 *   published  (Opublikowana)   – widoczna na stronie głównej (i w przyszłej aplikacji),
 *                                 o ile mieści się w terminie dostępności.
 *   hidden     (Ukryta)         – czasowo zdjęta z publikacji; można ją poprawiać i opublikować ponownie.
 *   archived   (Zarchiwizowana) – zakończona, tylko do odczytu; można ją przywrócić jako szkic.
 */
final class GameStatusService
{
    /** Dozwolone przejścia: obecny status => statusy docelowe. */
    public const TRANSITIONS = [
        'draft'     => ['published', 'archived'],
        'published' => ['hidden', 'draft', 'archived'],
        'hidden'    => ['published', 'draft', 'archived'],
        'archived'  => ['draft'],
    ];

    /**
     * Sprawdza, czy zmiana jest możliwa. Niedozwolone przejście kończy się wyjątkiem,
     * a braki w grze przed publikacją wracają jako lista błędów (format jak przy zapisie).
     */
    public function check(array $game, string $target): array
    {
        $from = (string) $game['status'];
        if (!isset(self::TRANSITIONS[$from])) {
            throw new HttpException(422, 'Nieznany status gry.');
        }
        if ($target === $from) {
            throw new HttpException(409, 'Gra ma już ten status. Odśwież stronę edytora.');
        }
        if (!in_array($target, self::TRANSITIONS[$from], true)) {
            throw new HttpException(422, 'Takiej zmiany statusu nie można wykonać.');
        }
        if ($target !== 'published') {
            return [];
        }

        // Sprawdzamy grę dokładnie w takiej postaci, w jakiej jest zapisana w bazie.
        $validator = new GameValidationService();
        [$data, $errors] = $validator->normalize(GameSerializer::forEditor((int) $game['id']) ?? [], Category::allActive());
        return $errors ?: $validator->forPublish($data);
    }

    /** Zmienia status w transakcji, zapisuje historię i podbija lock_version. */
    public function change(array $game, string $target, int $lockVersion, int $userId): void
    {
        Database::transaction(function (PDO $pdo) use ($game, $target, $lockVersion, $userId): void {
            $stmt = $pdo->prepare(
                "UPDATE games
                    SET status = ?,
                        published_at = CASE WHEN ? = 'published' THEN COALESCE(published_at, NOW()) ELSE published_at END,
                        archived_at  = CASE WHEN ? = 'archived' THEN NOW() ELSE NULL END,
                        lock_version = lock_version + 1
                  WHERE id = ? AND lock_version = ? AND status = ?"
            );
            $stmt->execute([$target, $target, $target, (int) $game['id'], $lockVersion, $game['status']]);

            if ($stmt->rowCount() === 0) {
                throw new HttpException(409, 'Ta gra została w międzyczasie zmieniona w innym oknie lub przez inną osobę. '
                    . 'Odśwież stronę i spróbuj ponownie.');
            }

            $pdo->prepare('INSERT INTO game_status_history (game_id, changed_by, from_status, to_status) VALUES (?, ?, ?, ?)')
                ->execute([(int) $game['id'], $userId, $game['status'], $target]);
        });
    }
}
