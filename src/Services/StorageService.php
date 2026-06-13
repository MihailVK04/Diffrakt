<?php

declare(strict_types=1);

namespace Diffrakt\Services;

use Aws\S3\S3Client;
use RuntimeException;
use InvalidArgumentException;

class StorageService {

    private S3Client $client;
    private string $bucket;
    private string $urlBase;
    private array $allowedCategories = ['originals', 'thumbs', 'processed', 'avatars'];

    public function __construct() {
        $this->client = new S3Client([
            'version' => 'latest',
            'region' => $_ENV['AWS_REGION'],
            'credentials' => [
                'key' => $_ENV['AWS_ACCESS_KEY_ID'],
                'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
            ],
        ]);

        $this->bucket = $_ENV['S3_BUCKET'];
        $this->urlBase = rtrim($_ENV['S3_URL_BASE'], '/');
    }

    public function generateUuid(): string {
        $b = random_bytes(16);
        $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
        $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    public function put(string $key, $stream, string $mimeType = 'application/octet-stream'): void {
        $this->validateCategory($key);
        $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'Body' => $stream,
            'ContentType' => $mimeType,
        ]);
    }

    public function putFile(string $key, string $localPath, string $mimeType = 'image/jpeg'): void {
        $this->validateCategory($key);
        $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'SourceFile' => $localPath,
            'ContentType' => $mimeType,
        ]);
    }

    public function storeUploadedFile(array $fileInfo, string $category, string $extension): string {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $ext = strtolower(ltrim($extension, '.'));

        if (!in_array($ext, $allowedExtensions, true)) {
            throw new InvalidArgumentException("Disallowed file extension: {$ext}");
        }

        if (!in_array($category, $this->allowedCategories, true)) {
            throw new InvalidArgumentException("Unknown or disallowed storage category: {$category}");
        }

        $filename = $this->generateUuid() . '.' . $ext;
        $key = $category . '/' . $filename;

        $mimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
        ];

        $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'SourceFile' => $fileInfo['tmp_name'],
            'ContentType' => $mimeMap[$ext] ?? 'application/octet-stream',
        ]);

        return $key;
    }

    public function deleteFile(string $key): bool {
        $this->client->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => ltrim($key, '/'),
        ]);
        return true;
    }

    public function url(string $key): string {
        return $this->urlBase . '/' . ltrim($key, '/');
    }

    public function downloadTemp(string $key): string {
        $tmp = tempnam(sys_get_temp_dir(), 'diffrakt_');
        $this->client->getObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'SaveAs' => $tmp,
        ]);
        return $tmp;
    }

    private function validateCategory(string $key): void {
        $category = explode('/', $key)[0];
        if (!in_array($category, $this->allowedCategories, true)) {
            throw new InvalidArgumentException("Unknown or disallowed storage category in key: {$key}");
        }
    }
}