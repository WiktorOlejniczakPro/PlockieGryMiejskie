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
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\GameController;
use App\Controllers\HomeController;

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

// --- API panelu (wymaga zalogowania, JSON) -------------------------------
$router->get('/api/v1/admin/games/{id}', [AdminGameApi::class, 'show'], ['auth']);
$router->post('/api/v1/admin/games', [AdminGameApi::class, 'store'], ['auth']);
$router->put('/api/v1/admin/games/{id}', [AdminGameApi::class, 'update'], ['auth']);

// Kolejne etapy: podgląd, publikacja, duplikowanie, archiwizacja, zdjęcia,
// konto, FAQ oraz publiczne API /api/v1/games dla aplikacji mobilnej.
