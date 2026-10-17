<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;
use App\Models\Game;

/** Strona startowa panelu: liczniki gier i ostatnio edytowane gry (dane z bazy). */
final class DashboardController
{
    public function index(Request $request): void
    {
        $user = Auth::user();

        View::render('dashboard/index', [
            'pageTitle'  => 'Dashboard – PGM',
            'activeNav'  => 'dashboard',
            'user'       => $user,
            'counts'     => Game::statusCounts($user),
            'recent'     => Game::recentlyUpdated($user, 6),
        ], 'layouts/panel');
    }
}
