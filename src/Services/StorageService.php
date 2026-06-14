<?php
declare(strict_types=1);

namespace Diffrakt\Services;

use Aws\S3\S3Client;

class StorageService
{
    private S3Client $client;
    private string   $bucket;
    private string   $urlBase;

    public function __construct()
    {
        $this->client = new S3Client([
            'version'     => 'latest',
            'region'      => $_ENV['AWS_REGION'],
            'credentials' => [
                'key'    => $_ENV['AWS_ACCESS_KEY_ID'],
                'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
            ],
        ]);

        $this->bucket  = $_ENV['S3_BUCKET'];
        $this->urlBase = rtrim($_ENV['S3_URL_BASE'], '/');
    }

    public function put(string $key, mixed $stream, string $mimeType = 'application/octet-stream'): void
    {
        $this->client->putObject([
            'Bucket'      => $this->bucket,
            'Key'         => $key,
            'Body'        => $stream,
            'ContentType' => $mimeType,
        ]);
    }

    public function putFile(string $key, string $localPath, string $mimeType = 'image/jpeg'): void
    {
        $this->client->putObject([
            'Bucket'      => $this->bucket,
            'Key'         => $key,
            'SourceFile'  => $localPath,
            'ContentType' => $mimeType,
        ]);
    }

    public function delete(string $key): void
    {
        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
            ]);
        } catch (\Exception) {}
    }

    public function url(string $key): string
    {
        return $this->urlBase . '/' . ltrim($key, '/');
    }
    public function downloadTemp(string $key): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'diffrakt_');
        $this->client->getObject([
            'Bucket' => $this->bucket,
            'Key'    => $key,
            'SaveAs' => $tmp,
        ]);
        return $tmp;
    }

    public function storeUploadedFile(array $file, string $folder, string $ext): string
    {
        $key  = $folder . '/' . uniqid() . '.' . $ext;
        $mime = mime_content_type($file['tmp_name']) ?: 'application/octet-stream';
        $this->putFile($key, $file['tmp_name'], $mime);
        return $key;
    }

    public function deleteFile(string $key): void
    {
        $this->delete($key);
    }
}