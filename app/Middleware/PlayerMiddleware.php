<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Models\Player;

/** API aplikacji mobilnej: wymaga ważnego tokenu gracza (Authorization: Bearer …), inaczej 401. */
final class PlayerMiddleware
{
    public function handle(Request $request): void
    {
        $token = $request->bearerToken();
        $player = $token !== null ? Player::findByToken($token) : null;
        if ($player === null) {
            throw new HttpException(401, 'Brak lub nieprawidłowy token gracza.');
        }
        Player::touch($player);
        Player::setCurrent($player);
    }
}
