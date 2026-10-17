<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\HttpException;
use App\Core\Request;
use App\Models\Category;
use App\Models\GameSession;
use App\Services\PlayerGameSerializer;

/**
 * Publiczne API gier dla aplikacji mobilnej (bez logowania).
 * Zwraca wyłącznie gry opublikowane i dostępne w terminie – bez poprawnych odpowiedzi.
 *   GET /api/v1/categories
 *   GET /api/v1/games?category=historyczna&difficulty=easy&q=tum
 *   GET /api/v1/games/{id}
 *   GET /api/v1/games/{id}/ranking
 *   GET /api/v1/ranking
 */
final class PublicGameApi
{
    public function categories(Request $request): void
    {
        $data = array_map(static fn (array $c): array => ['slug' => $c['slug'], 'name' => $c['name']], Category::allActive());
        json_response(['data' => $data]);
    }

    public function index(Request $request): void
    {
        json_response(['data' => PlayerGameSerializer::list(
            self::stringParam($request, 'category'),
            self::stringParam($request, 'difficulty'),
            self::stringParam($request, 'q'),
        )]);
    }

    public function show(Request $request, int $id): void
    {
        $game = PlayerGameSerializer::details($id) ?? throw new HttpException(404, 'Ta gra nie jest dostępna.');
        json_response(['data' => $game]);
    }

    public function ranking(Request $request, int $id): void
    {
        if (PlayerGameSerializer::details($id) === null) {
            throw new HttpException(404, 'Ta gra nie jest dostępna.');
        }
        json_response(['data' => GameSession::gameRanking($id)]);
    }

    public function overallRanking(Request $request): void
    {
        json_response(['data' => GameSession::overallRanking()]);
    }

    private static function stringParam(Request $request, string $key): ?string
    {
        $value = $request->queryParam($key);
        return is_string($value) ? $value : null;
    }
}
