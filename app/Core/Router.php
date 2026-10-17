<?php
declare(strict_types=1);

namespace App\Core;

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;

/**
 * Minimalny router: metoda + wzorzec ścieżki + kontroler + middleware.
 * Parametry w ścieżce: {id} (liczba całkowita).
 * Każde żądanie zmieniające dane (POST/PUT/PATCH/DELETE) automatycznie
 * przechodzi przez weryfikację tokenu CSRF.
 */
final class Router
{
    /** @var array<int, array{method:string, regex:string, handler:array, middleware:array}> */
    private array $routes = [];

    private const MIDDLEWARE = [
        'auth'  => AuthMiddleware::class,
        'guest' => GuestMiddleware::class,
    ];

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, array $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, array $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>\d+)', rtrim($path, '/') ?: '/') . '$#';
        $this->routes[] = compact('method', 'regex', 'handler', 'middleware');
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method === 'HEAD' ? 'GET' : $request->method;
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $matches)) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $method) {
                continue;
            }

            if (!$request->isSafeMethod()) {
                (new CsrfMiddleware())->handle($request);
            }
            foreach ($route['middleware'] as $name) {
                $class = self::MIDDLEWARE[$name] ?? null;
                if ($class === null) {
                    throw new \LogicException("Nieznany middleware: {$name}");
                }
                (new $class())->handle($request);
            }

            $params = array_map('intval', array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
            [$class, $action] = $route['handler'];
            (new $class())->$action($request, ...$params);
            return;
        }

        throw new HttpException($pathMatched ? 405 : 404);
    }
}
