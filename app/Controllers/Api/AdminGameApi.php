<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Models\Category;
use App\Services\AuditLog;
use App\Services\GameAccess;
use App\Services\GameSaveService;
use App\Services\GameSerializer;
use App\Services\GameStatusService;
use App\Services\GameValidationService;

/**
 * API panelu dla edytora gry (wymaga zalogowania – middleware 'auth').
 *   GET  /api/v1/admin/games/{id}  – pełne dane gry do edycji
 *   POST /api/v1/admin/games       – utworzenie gry (szkic)
 *   PUT  /api/v1/admin/games/{id}  – zapis zmian (z kontrolą lock_version)
 *   POST /api/v1/admin/games/{id}/status – publikacja / powrót do szkicu / archiwizacja {status, lock_version}
 * Odpowiedzi: {"data": {...}} albo {"error": {"status", "message", "fields"?}}.
 */
final class AdminGameApi
{
    public function show(Request $request, int $id): void
    {
        GameAccess::editable($id);
        json_response(['data' => GameSerializer::forEditor($id)]);
    }

    public function store(Request $request): void
    {
        $user = Auth::user();
        $data = $this->validated($request, false);

        $id = (new GameSaveService())->save(null, (int) $user['id'], $data, null, (int) $user['id']);
        AuditLog::record('game.created', (int) $user['id'], 'game', $id);

        json_response(['data' => GameSerializer::forEditor($id)], 201);
    }

    public function update(Request $request, int $id): void
    {
        $game = GameAccess::editable($id);
        // Edytować można tylko szkic. Opublikowaną grę trzeba najpierw cofnąć do szkicu.
        if ($game['status'] === 'published') {
            throw new HttpException(409, 'Opublikowanej gry nie można edytować. Cofnij ją do szkicu w zakładce „Podgląd i publikacja”.');
        }
        if ($game['status'] === 'archived') {
            throw new HttpException(409, 'Zarchiwizowanej gry nie można edytować.');
        }

        $lockVersion = $request->input('lock_version');
        if (!is_numeric($lockVersion)) {
            throw new HttpException(422, 'Brak numeru wersji gry. Odśwież stronę edytora.');
        }

        $data = $this->validated($request, false);

        $userId = (int) Auth::id();
        (new GameSaveService())->save($id, (int) $game['owner_id'], $data, (int) $lockVersion, $userId);
        AuditLog::record('game.updated', $userId, 'game', $id, ['waypoints' => count($data['waypoints'])]);

        json_response(['data' => GameSerializer::forEditor($id)]);
    }

    public function changeStatus(Request $request, int $id): void
    {
        GameAccess::editable($id);
        $status = $request->input('status');
        $lockVersion = $request->input('lock_version');
        if (!in_array($status, ['published', 'draft', 'archived'], true) || !is_numeric($lockVersion)) {
            throw new HttpException(422, 'Nieprawidłowy status gry.');
        }

        $errors = (new GameStatusService())->change($id, $status, (int) $lockVersion, (int) Auth::id());
        if ($errors) {
            json_response(['error' => [
                'status'  => 422,
                'message' => 'Gry nie można jeszcze opublikować. Uzupełnij wskazane elementy i zapisz grę.',
                'fields'  => $errors,
            ]], 422);
        }
        json_response(['data' => GameSerializer::forEditor($id)]);
    }

    /** Walidacja; przy błędach kończy żądanie odpowiedzią 422 z listą pól. */
    private function validated(Request $request, bool $publishRules): array
    {
        $validator = new GameValidationService();
        [$data, $errors] = $validator->normalize($request->all(), Category::allActive());
        if (!$errors && $publishRules) {
            $errors = $validator->forPublish($data);
        }
        if ($errors) {
            json_response([
                'error' => [
                    'status'  => 422,
                    'message' => $publishRules && count($errors) > 0
                        ? 'Gra jest opublikowana, więc musi pozostać kompletna. Popraw wskazane elementy.'
                        : 'Popraw wskazane elementy i zapisz ponownie.',
                    'fields'  => $errors,
                ],
            ], 422);
        }
        return $data;
    }
}
