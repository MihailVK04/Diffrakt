<?php

declare(strict_types=1);

namespace Diffrakt\Services;

use RuntimeException;
use InvalidArgumentException;

class StorageService {
    
    private string $basePath;
    private array $allowedCategories = ['originals', 'thumbs', 'processed', 'avatars'];

    public function __construct() {
        $this->basePath = $_ENV['STORAGE_PATH'] ?? $_SERVER['STORAGE_PATH'] ?? dirname(__DIR__, 2) . '/storage';
    }

    public function generateUuid(): string {
        $b = random_bytes(16);
        $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
        $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    public function getPath(string $category, string $filename): string {
        if (!in_array($category, $this->allowedCategories, true)) {
            throw new InvalidArgumentException("Unknown or disallowed storage category: {$category}");
        }

        return $this->basePath . '/' . $category . '/' . basename($filename);
    }

    public function ensureDirectory(string $category): void {
        if (!in_array($category, $this->allowedCategories, true)) {
            throw new InvalidArgumentException("Unknown or disallowed storage category: {$category}");
        }

        $dir = $this->basePath . '/' . $category;
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException("Cannot create directory: {$dir}");
            }
        }
    }

    public function storeUploadedFile(array $fileInfo, string $category, string $extension): string {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $ext = strtolower(ltrim($extension, '.'));
        if (!in_array($ext, $allowedExtensions, true)) {
            throw new InvalidArgumentException("Disallowed file extension: {$ext}");
        }

        $filename = $this->generateUuid() . '.' . $ext;
        $destination = $this->getPath($category, $filename);
        
        $this->ensureDirectory($category);

        if (!move_uploaded_file($fileInfo['tmp_name'], $destination)) {
            throw new RuntimeException('Failed to move uploaded file to storage.');
        }

        return $category . '/' . $filename;
    }

    public function deleteFile(string $path): bool {
        $fullPath = $this->basePath . '/' . ltrim($path, '/');
        
        $realBase = realpath($this->basePath);
        $realFull = realpath($fullPath);

        if ($realBase === false || $realFull === false) {
            return false;
        }

        $baseWithSeparator = $realBase . DIRECTORY_SEPARATOR;

        if ($realFull !== $realBase && !str_starts_with($realFull, $baseWithSeparator)) {
            throw new RuntimeException('Path traversal attempt detected during file deletion.');
        }

        if (is_file($realFull)) {
            return unlink($realFull);
        }
        
        return false;
    }
}