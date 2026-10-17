<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Wczytuje plik .env (KLUCZ=wartość). Zmienne ustawione w systemie
 * mają pierwszeństwo przed plikiem.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $values = [];
    private static bool $loaded = false;

    public static function load(string $file): void
    {
        self::$loaded = is_file($file);
        if (!self::$loaded) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                continue;
            }
            // usuń cudzysłowy: KEY="wartość"
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
            }
            self::$values[$key] = $value;
        }
    }

    public static function isLoaded(): bool
    {
        return self::$loaded;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value === false) {
            $value = self::$values[$key] ?? null;
        }
        if ($value === null) {
            return $default;
        }

        return match (strtolower($value)) {
            'true'  => true,
            'false' => false,
            'null'  => null,
            ''      => $default ?? '',
            default => $value,
        };
    }
}
