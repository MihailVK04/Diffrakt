<?php

/**
 * src/Bootstrap.php — Application Bootstrap
 *
 * Wired up by public/index.php after autoloading is registered.
 *
 * Responsibilities (in order):
 * 1. Load environment variables from .env (if the file exists and values
 * are not already set — Docker may inject them directly).
 * 2. Establish the database connection (singleton via Database::getInstance).
 * 3. Build the Request object from the current HTTP context.
 * 4. Instantiate the Router, register every route, and dispatch.
 *
 * This file never returns — every code path ends with Response::json() (which
 * calls exit) or an uncaught exception that index.php's catch block handles.
 */

declare(strict_types=1);

use Diffrakt\Core\Database;
use Diffrakt\Core\RateLimiter;
use Diffrakt\Core\Request;
use Diffrakt\Core\Response;
use Diffrakt\Core\Router;
use Diffrakt\Core\Session;
use Diffrakt\Controllers\AuthController;
use Diffrakt\Controllers\FeedController;
use Diffrakt\Controllers\FilterController;
use Diffrakt\Controllers\PipelineController;
use Diffrakt\Controllers\PostController;
use Diffrakt\Controllers\UserController;

// ---------------------------------------------------------------------------
// 1. Load .env
//
// Only reads the file when running under Apache/XAMPP (local dev). In the
// Docker/production environment all variables arrive via the container's
// environment, so the .env file may not exist at all — that is fine.
// ---------------------------------------------------------------------------

$envFile = ROOT_PATH . '/.env';

if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments and malformed lines.
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Strip optional surrounding quotes.
        if (strlen($value) >= 2 && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))) {
            $value = substr($value, 1, -1);
        }

        if (!isset($_ENV[$key]) && getenv($key) === false) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

// ---------------------------------------------------------------------------
// 2. Database connection
//
// Database::getInstance() reads DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
// from $_ENV and returns a singleton PDO. Throws \RuntimeException on failure,
// which index.php catches and converts to a JSON 500.
// ---------------------------------------------------------------------------

$pdo = Database::getInstance()->getPdo();

// ---------------------------------------------------------------------------
// 3. Start session
//
// Must run after DB is connected (the handler needs PDO) and before the
// router dispatches (controllers may read $_SESSION immediately).
// ---------------------------------------------------------------------------

Session::start();

// ---------------------------------------------------------------------------
// 4. Build Request
// ---------------------------------------------------------------------------

$request = new Request();

// ---------------------------------------------------------------------------
// 5. Register routes and dispatch
//
// Route format: Router::add(method, pattern, [ControllerClass, method], $auth)
//
// Patterns use {placeholder} segments; the Router resolves them to named
// captures and passes them to the controller as $request->params.
//
// $auth = true  → Middleware::requireAuth() runs before the controller.
// $auth = false → Public route (no JWT check).
// ---------------------------------------------------------------------------

$rateLimiter = new RateLimiter($pdo);

$router = new Router($request, $rateLimiter);

$router->add('POST', '/api/v1/auth/register', [AuthController::class, 'register'], false, ['endpoint' => 'auth.register', 'max' => 5, 'window' => 60]);
$router->add('POST', '/api/v1/auth/login', [AuthController::class, 'login'], false, ['endpoint' => 'auth.login', 'max' => 10, 'window' => 60]);
$router->add('POST', '/api/v1/auth/logout', [AuthController::class, 'logout'], true, null);
$router->add('GET', '/api/v1/auth/me', [AuthController::class, 'me'], true, null);

$router->add('GET', '/api/v1/users/{username}', [UserController::class, 'profile'], false, null);
// НОВ РЕД ЗА SPEC 005: Endpoint за снимките на потребителя с пагинация
$router->add('PATCH', '/api/v1/users/me', [UserController::class, 'update'], true, null);
$router->add('GET', '/api/v1/users/{username}/posts', [UserController::class, 'posts'], false, null);
$router->add('POST', '/api/v1/users/{username}/follow', [UserController::class, 'follow'], true, null);
$router->add('DELETE', '/api/v1/users/{username}/follow', [UserController::class, 'unfollow'], true, null);

$router->add('POST', '/api/v1/posts', [PostController::class, 'upload'], true, ['endpoint' => 'posts.upload', 'max' => 20, 'window' => 60]);
$router->add('GET', '/api/v1/posts/{id}', [PostController::class, 'get'], false, null);
$router->add('PATCH', '/api/v1/posts/{id}', [PostController::class, 'update'], true, null);
$router->add('DELETE', '/api/v1/posts/{id}', [PostController::class, 'delete'], true, null);
$router->add('POST', '/api/v1/posts/{id}/export', [PostController::class, 'export'], true, null);

$router->add('GET', '/api/v1/filters', [FilterController::class, 'list'], false, null);
$router->add('GET', '/api/v1/filters/{id}', [FilterController::class, 'get'], false, null);
$router->add('POST', '/api/v1/filters', [FilterController::class, 'create'], true, null);
$router->add('DELETE', '/api/v1/filters/{id}', [FilterController::class, 'delete'], true, null);

$router->add('POST', '/api/v1/pipelines', [PipelineController::class, 'create'], true, null);
$router->add('GET', '/api/v1/pipelines/{id}', [PipelineController::class, 'get'], false, null);
$router->add('PUT', '/api/v1/pipelines/{id}/steps', [PipelineController::class, 'replaceSteps'], true, null);
$router->add('DELETE', '/api/v1/pipelines/{id}', [PipelineController::class, 'delete'], true, null);
$router->add('POST', '/api/v1/pipelines/{id}/apply', [PipelineController::class, 'apply'], true, ['endpoint' => 'pipelines.apply', 'max' => 20, 'window' => 60]);       // existing — post_id
$router->add('POST', '/api/v1/pipelines/{id}/preview', [PipelineController::class, 'preview'], true, ['endpoint' => 'pipelines.preview', 'max' => 60, 'window' => 60]);   // new — image_b64

$router->add('GET', '/api/v1/feed', [FeedController::class, 'index'], true, null);

// ---------------------------------------------------------------------------
// 6. Dispatch
//
// Router::dispatch() matches the request against the route table, runs
// Middleware if the route requires auth, instantiates the controller, and
// calls the action method. It ends with Response::json(), which calls exit.
//
// If no route matches, the Router calls Response::json(['error' => 'Not found'], 404).
// If auth fails, Middleware calls Response::json(['error' => 'Unauthorized'], 401).
// ---------------------------------------------------------------------------

$router->dispatch();
?>