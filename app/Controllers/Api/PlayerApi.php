<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\HttpException;
use App\Core\Request;
use App\Models\GameSession;
use App\Models\Player;
use App\Services\GamePlayService;

/**
 * Konto gracza i rozgrywka (aplikacja mobilna).
 *   POST /api/v1/players                                   – nowe konto, zwraca token (bez logowania)
 *   GET  /api/v1/me                                        – profil + historia rozgrywek
 *   PUT  /api/v1/me                                        – zmiana nicku
 *   POST /api/v1/games/{id}/sessions                       – start / wznowienie gry
 *   GET  /api/v1/sessions/{id}                             – stan rozgrywki
 *   POST /api/v1/sessions/{id}/waypoints/{waypointId}/arrival – „jestem na miejscu” (pozycja GPS)
 *   POST /api/v1/sessions/{id}/tasks/{taskId}/answer       – odpowiedź (JSON lub multipart z plikiem "media")
 * Wszystkie poza POST /players wymagają nagłówka "Authorization: Bearer <token>".
 */
final class PlayerApi
{
    public function register(Request $request): void
    {
        $limit = (int) config('app.player.max_new_players_per_ip_hour', 30);
        if (Player::createdRecentlyFromIp(Request::ip()) >= $limit) {
            throw new HttpException(429, 'Z tego urządzenia założono zbyt wiele kont. Spróbuj ponownie za godzinę.');
        }

        $nickname = $request->string('nickname');
        if ($nickname === '') {
            $nickname = 'Gracz ' . random_int(1000, 9999);
        }
        self::assertNickname($nickname);

        [$player, $token] = Player::create($nickname);
        json_response(['data' => ['player' => Player::publicData($player), 'token' => $token]], 201);
    }

    public function me(Request $request): void
    {
        $player = Player::current();
        json_response(['data' => [
            'player'  => Player::publicData($player),
            'history' => GameSession::historyForPlayer((int) $player['id']),
        ]]);
    }

    public function rename(Request $request): void
    {
        $player = Player::current();
        $nickname = $request->string('nickname');
        self::assertNickname($nickname);
        Player::rename((int) $player['id'], $nickname);
        json_response(['data' => ['player' => Player::publicData(['nickname' => $nickname] + $player)]]);
    }

    public function startSession(Request $request, int $id): void
    {
        json_response(['data' => (new GamePlayService())->start($id, self::playerId())], 201);
    }

    public function showSession(Request $request, int $id): void
    {
        json_response(['data' => (new GamePlayService())->state($id, self::playerId())]);
    }

    public function arrive(Request $request, int $id, int $waypointId): void
    {
        $lat = $request->input('latitude');
        $lng = $request->input('longitude');
        $accuracy = $request->input('accuracy_m');
        if (!is_numeric($lat) || !is_numeric($lng)) {
            throw new HttpException(422, 'Brak pozycji GPS (latitude, longitude).');
        }
        json_response(['data' => (new GamePlayService())->arrive(
            $id, self::playerId(), $waypointId, (float) $lat, (float) $lng,
            is_numeric($accuracy) ? (int) round((float) $accuracy) : null
        )]);
    }

    public function answer(Request $request, int $id, int $taskId): void
    {
        json_response(['data' => (new GamePlayService())->answer(
            $id, self::playerId(), $taskId, $request->all(), $request->file('media')
        )]);
    }

    private static function playerId(): int
    {
        return (int) Player::current()['id'];
    }

    private static function assertNickname(string $nickname): void
    {
        if (!preg_match(Player::NICKNAME_PATTERN, $nickname)) {
            throw new HttpException(422, 'Nick musi mieć 3–40 znaków: litery, cyfry, spacje, kropki, myślniki lub podkreślenia.');
        }
    }
}
