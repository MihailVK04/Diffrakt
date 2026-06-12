<?php

declare(strict_types=1);

namespace Diffrakt\Core;

class Request {

    public array $params = [];
    private ?array $bodyCache = null;

    public function method(): string {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function uri(): string {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        $pos = strpos($uri, '?');
        if ($pos !== false) {
            $uri = substr($uri, 0, $pos);
        }

        $base = rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/\\');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        $uri = '/' . ltrim($uri, '/');
        $uri = rtrim($uri, '/') ?: '/';

        return $uri;
    }

    public function query(?string $key = null, mixed $default = null): mixed {
        if ($key === null) {
            return $_GET ?? [];
        }

        return isset($_GET[$key]) ? (string) $_GET[$key] : $default;
    }

    public function body(): ?array {
        if ($this->bodyCache !== null) {
            return $this->bodyCache;
        }

        $contentType = $this->header('Content-Type') ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');

            if ($raw === false || $raw === '') {
                $this->bodyCache = [];
                return $this->bodyCache;
            }

            $decode = json_decode($raw, associative: true);

            $this->bodyCache = is_array($decode) ? $decode : [];
            return $this->bodyCache;
        }

        $this->bodyCache = $_POST ?? [];
        return $this->bodyCache;
    }

    public function input(string $key, mixed $default = null): mixed {
        return $this->body()[$key] ?? $default;
    }

    public function file(string $key): ?array {
        if (!isset($_FILES[$key])) {
            return null;
        }

        $file = $_FILES[$key];

        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $file;
    }

    public function header(string $name): ?string {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();

            foreach ($headers as $key => $value) {
                if (strcasecmp($key, $name) === 0) {
                    return $value;
                }
            }

            return null;
        }

        $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        if (in_array(strtoupper($name), ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
            $normalized = strtoupper(str_replace('-', '_', $name));
        }

        return isset($_SERVER[$normalized]) ? (string) $_SERVER[$normalized] : null;
    }

    public function ip(): string {

        $isProduction = ($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'production';
        
        if ($isProduction) {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
            if ($forwarded !== null) {
                return trim(explode(',', $forwarded)[0]);
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userId(): ?int {
        $id = $_SESSION['user_id'] ?? null;

        return $id !== null ? (int) $id : null;
    }

    public function username(): string {
        return (string) ($_SESSION['username'] ?? '');
    }

    public function setParams(array $params): void {
        $this->params = $params;
    }
}
?>