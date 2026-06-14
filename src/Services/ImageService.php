<?php
declare(strict_types=1);

namespace Diffrakt\Services;

class ImageService {
    private StorageService $storage;

    public function __construct(StorageService $storage) {
        $this->storage = $storage;
    }

    public function processUpload(array $uploadedFile): array {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($uploadedFile['tmp_name']);
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        
        if (!\in_array($mime, $allowed)) {
            throw new \InvalidArgumentException('Invalid file type.');
        }

        $info = \getimagesize($uploadedFile['tmp_name']);
        if ($info && ($info[0] * $info[1] > 20000000)) { 
            throw new \RuntimeException('Image too large for processing.');
        }

        $extension = \pathinfo($uploadedFile['name'], PATHINFO_EXTENSION) ?: 'jpg';
        
        $originalPath = $this->storage->storeUploadedFile($uploadedFile, 'originals', $extension);

        $tmpThumb = tempnam(sys_get_temp_dir(), 'diffrakt_thumb_');
        $this->generateThumbnail($uploadedFile['tmp_name'], $tmpThumb);

        $thumbKey = 'thumbs/' . \bin2hex(\random_bytes(16)) . '.jpg';
        $this->storage->putFile($thumbKey, $tmpThumb, 'image/jpeg');
        unlink($tmpThumb);

        return ['original' => $originalPath, 'thumb' => $thumbKey];
    }

    public function generateThumbnail(string $sourceFile, string $destFile): void {
        $image = \imagecreatefromstring(\file_get_contents($sourceFile));

        if (!$image) {
            throw new \RuntimeException('Failed to read image data.');
        }

        $width = \imagesx($image);
        $height = \imagesy($image);
        $newWidth = 800;

        if ($width > $newWidth) {
            $newHeight = (int)($height * ($newWidth / $width));
            $scaledImage = \imagescale($image, $newWidth, $newHeight);
            if ($scaledImage !== false) {
                $image = $scaledImage;
            }
        }

        \imagejpeg($image, $destFile, 85);
    }

    public function serveFile(string $absolutePath, string $mime): void {
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . \filesize($absolutePath));
        \readfile($absolutePath);
        exit;
    }
}