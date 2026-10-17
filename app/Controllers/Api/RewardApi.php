<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Models\Player;
use App\Services\RewardService;

/**
 * Sklep punktów (aplikacja mobilna, token gracza).
 *   GET  /api/v1/rewards               – nagrody + saldo
 *   POST /api/v1/rewards/{id}/redeem   – wymiana {idempotency_key}
 *   GET  /api/v1/me/redemptions        – historia wymian z kodami
 *   GET  /api/v1/me/balance            – saldo punktów
 */
final class RewardApi
{
    public function index(Request $request): void
    {
        $playerId = (int) Player::current()['id'];
        json_response(['data' => [
            'balance' => RewardService::balance($playerId),
            'rewards' => RewardService::listForPlayer($playerId),
        ]]);
    }

    public function redeem(Request $request, int $id): void
    {
        $key = $request->input('idempotency_key');
        json_response(['data' => (new RewardService())->redeem(
            (int) Player::current()['id'], $id, is_string($key) && $key !== '' ? $key : null
        )], 201);
    }

    public function redemptions(Request $request): void
    {
        json_response(['data' => RewardService::redemptionsForPlayer((int) Player::current()['id'])]);
    }

    public function balance(Request $request): void
    {
        json_response(['data' => RewardService::balance((int) Player::current()['id'])]);
    }
}
