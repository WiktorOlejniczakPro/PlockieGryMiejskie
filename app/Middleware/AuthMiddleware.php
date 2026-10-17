<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;

/** Panel działa tylko wtedy, gdy w .env wskazano istniejące, aktywne konto. */
final class AuthMiddleware
{
    public function handle(Request $request): void
    {
        if (Auth::check()) {
            return;
        }
        throw new HttpException(500, 'Brak konta dla panelu. Utwórz konto skryptem bin/create-admin.php '
            . 'i wpisz jego adres e-mail w pliku .env jako AUTO_LOGIN_EMAIL.');
    }
}