<?php
declare(strict_types=1);

namespace Diffrakt\Controllers;

use Diffrakt\Core\Request;
use Diffrakt\Core\Response;
use Diffrakt\Services\StorageService;

class FileController {
    public function serve(Request $request): void {
        $path = $_GET['path'] ?? '';

        if ($path === '') {
            Response::notFound('No path specified.');
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');

        $allowed = ['originals', 'thumbs', 'processed', 'avatars'];
        $parts = explode('/', $path, 2);

        if (count($parts) !== 2 || !in_array($parts[0], $allowed, true)) {
            Response::notFound('File not found.');
        }

        $storage = new StorageService();
        $absolutePath = $storage->getPath($parts[0], $parts[1]);

        if (!file_exists($absolutePath) || !is_file($absolutePath)) {
            Response::notFound('File not found.');
        }

        $mime = mime_content_type($absolutePath) ?: 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($absolutePath));
        header('Cache-Control: public, max-age=31536000');
        header('Access-Control-Allow-Origin: *');
        readfile($absolutePath);
        exit;
    }
}