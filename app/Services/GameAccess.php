<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\HttpException;
use App\Models\Game;

/**
 * Kontrola dostępu do gier – w JEDNYM miejscu, używana przez strony i API.
 * Grę może edytować jej właściciel albo administrator.
 */
final class GameAccess
{
    /** Zwraca grę, jeśli zalogowany użytkownik może ją edytować; w przeciwnym razie 404/403. */
    public static function editable(int $gameId): array
    {
        $game = Game::findById($gameId);
        if ($game === null) {
            throw new HttpException(404, 'Nie znaleziono gry.');
        }
        if (!self::canEdit(Auth::user() ?? [], $game)) {
            throw new HttpException(403, 'Nie masz uprawnień do edycji tej gry.');
        }
        return $game;
    }

    public static function canEdit(array $user, array $game): bool
    {
        if (($user['role'] ?? '') === 'admin') {
            return true;
        }
        return isset($user['id']) && (int) $game['owner_id'] === (int) $user['id'];
    }
}
