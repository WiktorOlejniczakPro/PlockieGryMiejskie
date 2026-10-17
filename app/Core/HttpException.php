<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Błąd HTTP z kodem (403, 404, 419…) i komunikatem bezpiecznym do pokazania. */
final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status);
    }

    public static function defaultMessage(int $status): string
    {
        return match ($status) {
            401 => 'Wymagane jest zalogowanie.',
            403 => 'Nie masz uprawnień do tej operacji.',
            404 => 'Nie znaleziono strony.',
            405 => 'Ta operacja nie jest dostępna pod tym adresem.',
            419 => 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.',
            422 => 'Przesłane dane są nieprawidłowe.',
            429 => 'Zbyt wiele żądań. Spróbuj ponownie za chwilę.',
            default => 'Wystąpił nieoczekiwany błąd. Spróbuj ponownie później.',
        };
    }
}
