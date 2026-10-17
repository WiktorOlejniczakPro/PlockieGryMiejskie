<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;

/** Strony tylko dla niezalogowanych (np. logowanie) – zalogowany trafia do panelu. */
final class GuestMiddleware
{
    public function handle(Request $request): void
    {
        if (Auth::check()) {
            redirect('/panel');
        }
    }
}
