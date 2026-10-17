<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;

/** Dostęp tylko dla zalogowanych. Strony -> przekierowanie do logowania, API -> 401. */
final class AuthMiddleware
{
    public function handle(Request $request): void
    {
        if (Auth::check()) {
            return;
        }
        if ($request->isApi()) {
            throw new HttpException(401);
        }
        if ($request->method === 'GET') {
            Session::set('_intended', $request->path);
        }
        Session::flash('info', 'Zaloguj się, aby przejść do panelu.');
        redirect('/logowanie');
    }
}
