<?php
declare(strict_types=1);

/**
 * Wszystkie trasy aplikacji.
 * Middleware: 'auth' – tylko zalogowani, 'guest' – tylko niezalogowani.
 * Weryfikacja CSRF dla POST/PUT/DELETE działa automatycznie (Router).
 *
 * @var App\Core\Router $router
 */

use App\Controllers\Api\AdminGameApi;
use App\Controllers\Api\PlaceApi;
use App\Controllers\Api\PlayerApi;
use App\Controllers\Api\PublicGameApi;
use App\Controllers\Api\RewardApi;
use App\Controllers\Api\RouteApi;
use App\Controllers\DashboardController;
use App\Controllers\GameController;
use App\Controllers\HomeController;
use App\Controllers\RewardController;

// --- Strona publiczna -----------------------------------------------------
$router->get('/', [HomeController::class, 'index']);

// --- Logowanie ------------------------------------------------------------

// --- Panel twórcy (wymaga zalogowania) ------------------------------------
$router->get('/panel', [DashboardController::class, 'index'], ['auth']);
$router->get('/panel/gry', [GameController::class, 'index'], ['auth']);
$router->get('/panel/gry/nowa', [GameController::class, 'create'], ['auth']);
$router->get('/panel/gry/{id}/edycja', [GameController::class, 'edit'], ['auth']);
$router->get('/panel/nagrody', [RewardController::class, 'index'], ['auth']);
$router->get('/panel/nagrody/nowa', [RewardController::class, 'create'], ['auth']);
$router->post('/panel/nagrody', [RewardController::class, 'store'], ['auth']);
$router->get('/panel/nagrody/{id}/edycja', [RewardController::class, 'edit'], ['auth']);
$router->put('/panel/nagrody/{id}', [RewardController::class, 'update'], ['auth']);
$router->delete('/panel/nagrody/{id}', [RewardController::class, 'destroy'], ['auth']);
$router->post('/panel/nagrody/{id}/status', [RewardController::class, 'toggle'], ['auth']);
$router->post('/panel/nagrody/{id}/kody', [RewardController::class, 'addCodes'], ['auth']);
$router->delete('/panel/nagrody/{id}/kody/{codeId}', [RewardController::class, 'deleteCode'], ['auth']);

// --- API panelu (wymaga zalogowania, JSON) -------------------------------
$router->get('/api/v1/admin/games/{id}', [AdminGameApi::class, 'show'], ['auth']);
$router->post('/api/v1/admin/games', [AdminGameApi::class, 'store'], ['auth']);
$router->put('/api/v1/admin/games/{id}', [AdminGameApi::class, 'update'], ['auth']);
$router->post('/api/v1/admin/games/{id}/status', [AdminGameApi::class, 'changeStatus'], ['auth']);
$router->post('/api/v1/admin/route', [RouteApi::class, 'walking'], ['auth']);

// --- API aplikacji mobilnej (JSON) ----------------------------------------
// Publiczne: tylko gry opublikowane, bez poprawnych odpowiedzi.
$router->get('/api/v1/categories', [PublicGameApi::class, 'categories']);
$router->get('/api/v1/games', [PublicGameApi::class, 'index']);
$router->get('/api/v1/games/{id}', [PublicGameApi::class, 'show']);
$router->get('/api/v1/games/{id}/ranking', [PublicGameApi::class, 'ranking']);
$router->get('/api/v1/ranking', [PublicGameApi::class, 'overallRanking']);
$router->get('/api/v1/place-categories', [PlaceApi::class, 'categories']);
$router->get('/api/v1/places', [PlaceApi::class, 'index']);

// Gracz: konto zakładane automatycznie przez aplikację, dalej token w nagłówku
// "Authorization: Bearer <token>" (middleware 'player'). Bez sesji i bez CSRF.
$router->post('/api/v1/players', [PlayerApi::class, 'register']);
$router->get('/api/v1/me', [PlayerApi::class, 'me'], ['player']);
$router->put('/api/v1/me', [PlayerApi::class, 'rename'], ['player']);
$router->get('/api/v1/me/limits', [PlayerApi::class, 'limits'], ['player']);
$router->get('/api/v1/me/balance', [RewardApi::class, 'balance'], ['player']);
$router->get('/api/v1/me/redemptions', [RewardApi::class, 'redemptions'], ['player']);
$router->get('/api/v1/rewards', [RewardApi::class, 'index'], ['player']);
$router->post('/api/v1/rewards/{id}/redeem', [RewardApi::class, 'redeem'], ['player']);
$router->post('/api/v1/games/{id}/sessions', [PlayerApi::class, 'startSession'], ['player']);
$router->get('/api/v1/sessions/{id}', [PlayerApi::class, 'showSession'], ['player']);
$router->post('/api/v1/sessions/{id}/waypoints/{waypointId}/arrival', [PlayerApi::class, 'arrive'], ['player']);
$router->post('/api/v1/sessions/{id}/tasks/{taskId}/answer', [PlayerApi::class, 'answer'], ['player']);

// Kolejne etapy panelu: duplikowanie, zdjęcia,
// konto, FAQ, ocena zdjęć i filmów przesłanych przez graczy.
