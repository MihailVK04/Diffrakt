<?php

declare(strict_types=1);

namespace Diffrakt\Core;

class Response {
    
    public static function json(array $data, int $status = 200): never {
        http_response_code($status);

        header('Content-Type: application/json; charset=UTF-8');

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        exit;
    }

    public static function file(string $absolutePath, string $downloadName, bool $inline = false): never {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            self::json(['error' => 'File not found'], 404);
        }

        $mime = self::detectMime($absolutePath);
        $size = filesize($absolutePath);
        $disposition = $inline ? 'inline' : 'attachment';

        http_response_code(200);

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . $size);
        header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($downloadName) . '"');
        header('Cache-Control: private, max-age=0');
        header('X-Content-Type-Options: nosniff');

        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        readfile($absolutePath);

        exit;
    }

    public static function noContent(): never {
        http_response_code(204);
        exit;
    }

    public static function badRequest(string $message = 'Bad request'): never {
        self::json(['error' => $message], 400);
    }

    public static function unauthorized(string $message = 'Unauthorized'): never {
        self::json(['error' => $message], 401);
    }

    public static function forbidden(string $message = 'Forbidden'): never {
        self::json(['error' => $message], 403);
    }

    public static function notFound(string $message = 'Not Found'): never {
        self::json(['error' => $message], 404);
    }

    public static function conflict(string $message = 'Conflict'): never {
        self::json(['error' => $message], 409);
    }

    public static function unprocessable(array $errors): never {
        self::json(['error' => 'Validation failed', 'errors' => $errors], 422);
    }

    public static function tooManyRequests(string $message = 'Too many requests'): never {
        self::json(['error' => $message], 429);
    }

    public static function serverError(string $message = 'Internal server error'): never {
        self::json(['error' => $message], 500);
    }

    private static function detectMime(string $path): string {
        if (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $path);
            finfo_close($finfo);

            if ($mime !== false && $mime !== 'application/octet-stream') {
                return $mime;
            }
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }
}
?>