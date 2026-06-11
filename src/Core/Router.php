<?php

/**
 * src/Core/Router.php
 *
 * Regex-based route table. Registered routes are matched against the
 * incoming method + URI; on a match the optional auth middleware runs,
 * then the controller action is called.
 *
 * Usage (in Bootstrap.php):
 *
 *   $router = new Router($request);
 *   $router->add('GET', '/api/v1/posts/{id}', [PostController::class, 'get'], false);
 *   $router->dispatch();
 *
 * Pattern syntax:
 *   {placeholder}  — matches one URI segment ([^/]+) and exposes the
 *                    captured value as $request->params['placeholder'].
 */

declare(strict_types=1);

namespace Diffrakt\Core;

class Router {

    /**
     * Registered routes.
     *
     * Each entry:
     *   [
     *     'method'  => 'GET',
     *     'regex'   => '#^/api/v1/posts/(?P<id>[^/]+)$#',
     *     'handler' => [PostController::class, 'get'],
     *     'auth'    => false,
     *   ]
     *
     * @var array<int, array{method: string, regex: string, handler: array{0: string, 1: string}, auth: bool}>
     */

    private array $routes = [];
    private Middleware $middleware;

    public function __construct(private readonly Request $request, RateLimiter $rateLimiter) {
        $this->middleware = new Middleware($rateLimiter);
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------
 
    /**
     * Register a route.
     *
     * @param string               $method   HTTP verb (GET, POST, PATCH, PUT, DELETE).
     * @param string               $pattern  URI pattern, e.g. '/api/v1/users/{username}'.
     * @param array{0:string,1:string} $handler  [ControllerClass::class, 'methodName'].
     * @param ?array $rateLimit  ['endpoint' => string, 'max' => int, 'window' => int] or null
     */

    public function add(string $method, string $pattern, array $handler, bool $auth, ?array $rateLimit = null): void {
        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => $this->patternToRegex($pattern),
            'handler' => $handler,
            'auth' => $auth,
            'rateLimit' => $rateLimit,
        ];
    }

    /**
     * Match the current request against the route table and dispatch.
     *
     * Tries every route in registration order (FIFO), which is why
     * Bootstrap registers '/users/me' before '/users/{username}'.
     *
     * Never returns — every path ends with Response::json() / exit.
     */

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

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------
 
    /**
     * Convert a route pattern into a named-capture regex.
     *
     * '/api/v1/users/{username}/follow'
     *   → '#^/api/v1/users/(?P<username>[^/]+)/follow$#'
     */

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

    /**
     * Emit CORS headers.
     *
     * In production the allowed origin should be locked to the actual
     * frontend domain via the APP_ORIGIN env var. During local dev '*' is
     * fine because both front and back run under the same XAMPP vhost.
     */
    private function sendCors(): void
    {
        $origin = $_ENV['APP_ORIGIN'] ?? getenv('APP_ORIGIN') ?: '*';
 
        header('Access-Control-Allow-Origin: '  . $origin);
        header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 86400');
    }
}
?>