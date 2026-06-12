<?php

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
use Diffrakt\Controllers\FileController;
use Diffrakt\Controllers\ReactionController;
use Diffrakt\Controllers\CommentController;

$envFile = ROOT_PATH . '/.env';

if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (strlen($value) >= 2 && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))) {
            $value = substr($value, 1, -1);
        }

        if (!isset($_ENV[$key]) && getenv($key) === false) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

$pdo = Database::getInstance()->getPdo();

Session::start();

$request = new Request();

$rateLimiter = new RateLimiter($pdo);

$router = new Router($request, $rateLimiter);

$router->add('POST', '/api/v1/auth/register', [AuthController::class, 'register'], false, ['endpoint' => 'auth.register', 'max' => 5, 'window' => 60]);
$router->add('POST', '/api/v1/auth/login', [AuthController::class, 'login'], false, ['endpoint' => 'auth.login', 'max' => 10, 'window' => 60]);
$router->add('POST', '/api/v1/auth/logout', [AuthController::class, 'logout'], true, null);
$router->add('GET', '/api/v1/auth/me', [AuthController::class, 'me'], true, null);

$router->add('GET', '/api/v1/users/search', [UserController::class, 'search'], false);
$router->add('GET', '/api/v1/users/{username}', [UserController::class, 'profile'], false, null);
$router->add('POST', '/api/v1/users/me', [UserController::class, 'update'], true, null);
$router->add('GET', '/api/v1/users/{username}/posts', [UserController::class, 'posts'], false, null);
$router->add('POST', '/api/v1/users/{username}/follow', [UserController::class, 'follow'], true, null);
$router->add('DELETE', '/api/v1/users/{username}/follow', [UserController::class, 'unfollow'], true, null);

$router->add('POST', '/api/v1/posts', [PostController::class, 'upload'], true, ['endpoint' => 'posts.upload', 'max' => 20, 'window' => 60]);
$router->add('GET', '/api/v1/posts/{id}', [PostController::class, 'get'], false, null);
$router->add('PATCH', '/api/v1/posts/{id}', [PostController::class, 'update'], true, null);
$router->add('DELETE', '/api/v1/posts/{id}', [PostController::class, 'delete'], true, null);
$router->add('POST', '/api/v1/posts/{id}/export', [PostController::class, 'export'], true, null);
$router->add('POST', '/api/v1/posts/{id}/publish', [PostController::class, 'publish'], true, null);

$router->add('GET', '/api/v1/filters', [FilterController::class, 'list'], false, null);
$router->add('GET', '/api/v1/filters/{id}', [FilterController::class, 'get'], false, null);
$router->add('POST', '/api/v1/filters', [FilterController::class, 'create'], true, null);
$router->add('DELETE', '/api/v1/filters/{id}', [FilterController::class, 'delete'], true, null);

$router->add('POST', '/api/v1/pipelines', [PipelineController::class, 'create'], true, null);
$router->add('GET', '/api/v1/pipelines/{id}', [PipelineController::class, 'get'], false, null);
$router->add('PUT', '/api/v1/pipelines/{id}/steps', [PipelineController::class, 'replaceSteps'], true, null);
$router->add('DELETE', '/api/v1/pipelines/{id}', [PipelineController::class, 'delete'], true, null);
$router->add('POST', '/api/v1/pipelines/{id}/apply', [PipelineController::class, 'apply'], true, ['endpoint' => 'pipelines.apply', 'max' => 20, 'window' => 60]);
$router->add('POST', '/api/v1/pipelines/{id}/preview', [PipelineController::class, 'preview'], true, ['endpoint' => 'pipelines.preview', 'max' => 60, 'window' => 60]);

$router->add('GET', '/api/v1/feed', [FeedController::class, 'index'], true, null);

$router->add('GET', '/api/v1/files', [FileController::class, 'serve'], false, null);

$router->add('POST', '/api/v1/posts/{id}/react', [ReactionController::class, 'reactToPost'], true, ['endpoint' => 'reactions.post', 'max' => 30, 'window' => 60]);
$router->add('DELETE', '/api/v1/posts/{id}/react', [ReactionController::class, 'removePostReaction'], true, null);

$router->add('GET', '/api/v1/posts/{id}/comments', [CommentController::class, 'listByPost'], false, null);
$router->add('POST', '/api/v1/posts/{id}/comments', [CommentController::class, 'create'], true, ['endpoint' => 'comments.create', 'max' => 10, 'window' => 60]);
$router->add('PATCH', '/api/v1/comments/{id}', [CommentController::class, 'update'], true, ['endpoint' => 'comments.update', 'max' => 10, 'window' => 60]);
$router->add('DELETE', '/api/v1/comments/{id}', [CommentController::class, 'delete'], true, null);

$router->add('POST', '/api/v1/comments/{id}/react', [ReactionController::class, 'reactToComment'], true, ['endpoint' => 'reactions.comment', 'max' => 30, 'window' => 60]);
$router->add('DELETE', '/api/v1/comments/{id}/react', [ReactionController::class, 'removeCommentReaction'], true, null);

$router->dispatch();
?>