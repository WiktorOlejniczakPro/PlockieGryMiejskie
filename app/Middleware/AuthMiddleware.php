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
        throw new HttpException(500, 'Brak konta dla panelu – sprawdź połączenie z bazą (plik .env) i czy zaimportowano schema.sql. '
            . 'Diagnostyka: C:\xampp\php\php.exe bin\sprawdz-konto.php');
    }
}