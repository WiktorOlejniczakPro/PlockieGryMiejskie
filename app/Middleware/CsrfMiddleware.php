<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;

/**
 * Weryfikacja tokenu CSRF dla POST/PUT/PATCH/DELETE.
 * Uruchamiana automatycznie przez Router – nie trzeba jej dopisywać do tras.
 */
final class CsrfMiddleware
{
    public function handle(Request $request): void
    {
        $token = $request->header('X-CSRF-Token') ?? $request->input('_csrf');
        if (!Csrf::verify(is_string($token) ? $token : null)) {
            throw new HttpException(419);
        }
    }
}
