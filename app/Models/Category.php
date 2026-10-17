<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Słownik kategorii gier. */
final class Category
{
    /** @return list<array{id:int, slug:string, name:string, is_other:int}> */
    public static function allActive(): array
    {
        return Database::query(
            'SELECT id, slug, name, is_other FROM categories WHERE is_active = 1 ORDER BY position, name'
        )->fetchAll();
    }
}
