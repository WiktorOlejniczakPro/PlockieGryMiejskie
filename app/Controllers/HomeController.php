<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\View;
use App\Models\Game;

/** Publiczna strona projektu – bez żadnych funkcji tworzenia gier. */
final class HomeController
{
    public function index(Request $request): void
    {
        View::render('public/home', [
            'pageTitle' => 'Płockie Gry Miejskie – odkrywaj Płock w grach terenowych',
            'games'     => Game::publishedForPublic(6),
        ]);
    }
}
