<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;
use App\Models\Category;
use App\Models\Game;
use App\Services\GameAccess;
use App\Services\GameSerializer;

/** Strony panelu: lista gier i edytor gry. */
final class GameController
{
    public function index(Request $request): void
    {
        $user = Auth::user();
        View::render('games/index', [
            'pageTitle' => 'Twoje gry – PGM',
            'activeNav' => 'games',
            'user'      => $user,
            'games'     => Game::listForUser($user),
        ], 'layouts/panel');
    }

    public function create(Request $request): void
    {
        $this->renderEditor(null);
    }

    public function edit(Request $request, int $id): void
    {
        // Zarchiwizowana gra otwiera się tylko do odczytu (zakładka „Podgląd i publikacja”),
        // skąd można ją przywrócić jako szkic. Zapis blokuje także API (409).
        GameAccess::editable($id);
        $this->renderEditor(GameSerializer::forEditor($id));
    }

    private function renderEditor(?array $game): void
    {
        $map = config('app.map');
        $bootstrap = [
            'game'       => $game,
            'categories' => array_map(static fn (array $c): array => [
                'id' => (int) $c['id'], 'name' => $c['name'], 'is_other' => (bool) $c['is_other'],
            ], Category::allActive()),
            'map' => [
                'lat'         => $map['center_lat'],
                'lng'         => $map['center_lng'],
                'zoom'        => $map['zoom'],
                'tileUrl'     => $map['tile_url'],
                'attribution' => $map['attribution'],
                'routing'     => (string) ($map['routing_url'] ?? '') !== '',
                'routingAttribution' => $map['routing_attribution'] ?? '',
            ],
            'urls' => [
                'create'       => url('/api/v1/admin/games'),
                'game'         => url('/api/v1/admin/games/{id}'),
                'status'       => url('/api/v1/admin/games/{id}/status'),
                'editPage'     => url('/panel/gry/{id}/edycja'),
                'login'        => url('/panel'),
                'route'        => url('/api/v1/admin/route'),
                'status'       => url('/api/v1/admin/games/{id}/status'),
                'home'         => url('/#gry'),
            ],
        ];

        View::render('editor/edit', [
            'pageTitle'   => ($game ? $game['title'] : 'Nowa gra') . ' – edytor – PGM',
            'activeNav'   => $game ? 'games' : 'create',
            'game'        => $game,
            'categories'  => $bootstrap['categories'],
            'bootstrap'   => $bootstrap,
            'mainClass'   => 'panel-main--editor',
            'compactSidebar' => true,
            'extraStyles' => ['vendor/leaflet/leaflet.css', 'css/editor.css'],
            'extraScripts'=> ['vendor/leaflet/leaflet.js', 'js/editor.js'],
        ], 'layouts/panel');
    }
}
