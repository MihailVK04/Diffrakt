<?php

declare(strict_types=1);

$env = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development';

if ($env === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
}

ini_set('default_charset', 'UTF-8');

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE') ?: 'UTC');

define('ROOT_PATH', dirname(__DIR__));

define('SRC_PATH', ROOT_PATH . '/src');

define('STORAGE_PATH', $_ENV['STORAGE_PATH'] ?? getenv('STORAGE_PATH') ?: ROOT_PATH . '/storage');


spl_autoload_register(function (string $class): void{
    $prefix = 'Diffrakt\\';
    
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = SRC_PATH . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});


try {
    require_once SRC_PATH . '/Bootstrap.php';
} catch (\Throwable $e) {

    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');

    $body = ['error' => 'Internal server error'];

    if ($env !== 'production') {
        $body['debug'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString()),
        ];
    }

    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(1);
}
?>