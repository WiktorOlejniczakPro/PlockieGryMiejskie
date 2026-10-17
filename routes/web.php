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
use App\Controllers\Api\RouteApi;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\GameController;
use App\Controllers\HomeController;
use App\Controllers\RewardController;

// --- Strona publiczna -----------------------------------------------------
$router->get('/', [HomeController::class, 'index']);

// --- Logowanie ------------------------------------------------------------
$router->get('/logowanie', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/logowanie', [AuthController::class, 'login'], ['guest']);
$router->post('/wyloguj', [AuthController::class, 'logout'], ['auth']);

// --- Panel twórcy (wymaga zalogowania) ------------------------------------
$router->get('/panel', [DashboardController::class, 'index'], ['auth']);
$router->get('/panel/gry', [GameController::class, 'index'], ['auth']);
$router->get('/panel/gry/nowa', [GameController::class, 'create'], ['auth']);
$router->get('/panel/gry/{id}/edycja', [GameController::class, 'edit'], ['auth']);

// --- Kreator nagród (tworzenie i zmiany – tylko administrator) ----------
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

// Kolejne etapy: duplikowanie, archiwizacja, zdjęcia,
// konto, FAQ oraz publiczne API /api/v1/games dla aplikacji mobilnej.
