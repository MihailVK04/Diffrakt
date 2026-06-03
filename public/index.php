<?php

/**
 * public/index.php — Front Controller
 *
 * The only PHP file Apache/Nginx ever executes directly.
 * Every HTTP request that is not a static asset lands here.
 *
 * Responsibilities:
 *   1. Lock down the environment (error reporting, charset, timezone).
 *   2. Block any request that somehow bypasses the web-server rewrite and
 *      tries to reach a non-existent resource directly.
 *   3. Require Bootstrap.php, which wires up every service and runs the
 *      router. Nothing else happens here.
 *
 * This file is intentionally minimal — all real logic lives in src/.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// 1. Environment hardening
// ---------------------------------------------------------------------------

// In production APP_ENV is set to 'production' via the .env / Docker env var.
// Errors must never leak to the client in production.

$env = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development';

if ($env === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1')
}

// All responses are UTF-8 JSON — set the default charset globally.
ini_set('default_charset', 'UTF-8');

// Fix timezone so every created_at / NOW() call is consistent.
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE') ?: 'UTC');

// ---------------------------------------------------------------------------
// 2. Path constants
// ---------------------------------------------------------------------------

// Absolute path to the project root (one level above public/).
define('ROOT_PATH', dirname(__DIR__));

// Absolute path to the src/ directory.
define('SRC_PATH', ROOT_PATH . '/src');

// Absolute path to the storage/ directory (outside web root).
define('STORAGE_PATH', $_ENV['STORAGE_PATH'] ?? getenv('STORAGE_PATH') ?: ROOT_PATH . '/storage');

// ---------------------------------------------------------------------------
// 3. Simple PSR-4-style autoloader
//
// Maps the top-level namespace "Diffrakt\" to src/.
// e.g.  Diffrakt\Controllers\AuthController  →  src/Controllers/AuthController.php
//       Diffrakt\Core\Router                 →  src/Core/Router.php
// ---------------------------------------------------------------------------

spl_autoload_register(function (string $class): void{
    $prefix = 'Diffrakt\\';
    
    if (strncmp($class, $prefix, strlen($prefix) !== 0)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = SRC_PATH . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

// ---------------------------------------------------------------------------
// 4. Hand off to Bootstrap
//
// Bootstrap.php loads the .env file, creates the DB connection, instantiates
// the router, registers all routes, runs middleware, and dispatches to the
// correct controller. It never returns normally — every code path ends with
// a Response::json() call (which calls exit) or an uncaught exception that
// is caught below.
// ---------------------------------------------------------------------------

try {
    require_once SRC_PATH . '/Bootstrap.php';
} catch (\Throwable $e) {
    // Last-resort error handler.
    // If Bootstrap itself throws (e.g. DB connection failure at startup),
    // we still need to return valid JSON — the client must not receive an
    // HTML error page or a blank response.

    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');

    $body = ['error' => 'Internal server error'];

    // Expose details only in development so stack traces never reach production.
    if ($env !== 'production') {
        $body['debug'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("/n", $e->getTraceAsString()),
        ];
    }

    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(1);
}
?>