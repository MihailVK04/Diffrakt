<?php

declare(strict_types=1);

namespace Diffrakt\Core;

class Router {

    private array $routes = [];
    private Middleware $middleware;

    public function __construct(private readonly Request $request, RateLimiter $rateLimiter) {
        $this->middleware = new Middleware($rateLimiter);
    }

    public function add(string $method, string $pattern, array $handler, bool $auth, ?array $rateLimit = null): void {
        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => $this->patternToRegex($pattern),
            'handler' => $handler,
            'auth' => $auth,
            'rateLimit' => $rateLimit,
        ];
    }

    public function dispatch(): void {
        $method = $this->request->method();
        $uri = $this->request->uri();

        if ($method === 'OPTIONS') {
            $this->sendCors();
            Response::json([], 204);
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (!preg_match($route['regex'], $uri, $matches)) {
                continue;
            }

            $params = array_filter(
                $matches,
                static fn($key) => is_string($key),
                ARRAY_FILTER_USE_KEY
            );

            $params = array_map('urldecode', $params);

            $this->request->setParams($params);

            $this->sendCors();

            if ($route['auth']) {
                $this->middleware->requireAuth();
            }

            if ($route['rateLimit'] !== null) {
                $rl = $route['rateLimit'];
                $this->middleware->rateLimit($rl['endpoint'], $rl['max'], $rl['window']);
            }

            [$class, $action] = $route['handler'];
            $controller = new $class();
            $controller->$action($this->request);

            Response::json(['error' => 'Controller did not send a response'], 500);
        }

        $uriExists = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $uri)) {
                $uriExists = true;
                break;
            }
        }

        if ($uriExists) {
            Response::json(['error' => 'Method not allowed'], 405);
        }

        Response::json(['error' => 'Not found'], 404);
    }

    private function patternToRegex(string $pattern): string {
        $escaped = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}|([^{}]+)/',
            static function (array $m): string {
                if (!empty($m[1])) {
                    return '(?P<' . $m[1] . '>[^/]+)';
                }
                return preg_quote($m[2], '#');
            },
            $pattern
        );

        return '#^' . $escaped . '$#';
    }

    private function sendCors(): void
    {
        $origin = $_ENV['APP_ORIGIN'] ?? getenv('APP_ORIGIN') ?: '*';

        header('Access-Control-Allow-Origin: '  . $origin);
        header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
    }
}
?>