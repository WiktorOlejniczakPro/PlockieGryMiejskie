<?php
declare(strict_types=1);

namespace App\Core;

/** Dane bieżącego żądania HTTP w jednym miejscu. */
final class Request
{
    private function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query,
        private readonly array $body,
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $body = $_POST;
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            $body = is_array($decoded) ? $decoded : [];
        }

        // Formularze HTML znają tylko GET/POST – PUT/DELETE przez pole _method.
        if ($method === 'POST' && isset($body['_method'])) {
            $override = strtoupper((string) $body['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        $uriPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $base = self::basePath();
        if ($base !== '' && str_starts_with($uriPath, $base)) {
            $uriPath = substr($uriPath, strlen($base));
        }
        $path = '/' . trim(rawurldecode($uriPath), '/');

        return new self($method, $path, $_GET, $body);
    }

    /** Katalog, w którym leży index.php, np. "/pgm/public" (pusty w katalogu głównym). */
    public static function basePath(): string
    {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        return ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /** Tekstowe pole formularza po przycięciu spacji. */
    public function string(string $key): string
    {
        $value = $this->body[$key] ?? '';
        return is_string($value) ? trim($value) : '';
    }

    public function queryParam(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->body;
    }

    public function isApi(): bool
    {
        return str_starts_with($this->path, '/api/');
    }

    /**
     * API aplikacji mobilnej (/api/v1/... poza /api/v1/admin/).
     * Uwierzytelnia się tokenem w nagłówku Authorization, a nie ciasteczkiem
     * sesji – dlatego nie korzysta z sesji PHP ani z tokenów CSRF.
     */
    public function isPlayerApi(): bool
    {
        return str_starts_with($this->path, '/api/v1/') && !str_starts_with($this->path, '/api/v1/admin/')
            && $this->path !== '/api/v1/admin';
    }

    /** Token z nagłówka "Authorization: Bearer <token>" albo null. */
    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization') ?? (string) ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ($header === '' && function_exists('getallheaders')) {
            $headers = array_change_key_case((array) getallheaders(), CASE_LOWER);
            $header = (string) ($headers['authorization'] ?? '');
        }
        return preg_match('/^Bearer\s+([A-Za-z0-9]{20,128})$/', trim($header), $m) ? $m[1] : null;
    }

    /** Przesłany plik z formularza multipart (pole $key) albo null. */
    public function file(string $key): ?array
    {
        $file = $_FILES[$key] ?? null;
        return is_array($file) && isset($file['error']) && !is_array($file['error']) ? $file : null;
    }

    public function isSafeMethod(): bool
    {
        return in_array($this->method, ['GET', 'HEAD', 'OPTIONS'], true);
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($_SERVER[$key]) ? (string) $_SERVER[$key] : null;
    }

    public static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public static function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}
