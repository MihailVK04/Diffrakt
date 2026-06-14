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
    $mime  = $finfo->file($uploadedFile['tmp_name']);

    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])) {
        throw new \InvalidArgumentException('Invalid file type.');
    }

    $info = getimagesize($uploadedFile['tmp_name']);
    if ($info && ($info[0] * $info[1] > 20_000_000)) {
        throw new \RuntimeException('Image too large for processing.');
    }

    $extension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION) ?: 'jpg';

    $originalKey = $this->storage->storeUploadedFile($uploadedFile, 'originals', $extension);

    $tempOriginal = $this->storage->downloadTemp($originalKey);

    $tempThumb = tempnam(sys_get_temp_dir(), 'diffrakt_thumb_') . '.jpg';
    $this->generateThumbnail($tempOriginal, $tempThumb);

    $thumbKey = 'thumbs/' . bin2hex(random_bytes(16)) . '.jpg';
    $this->storage->putFile($thumbKey, $tempThumb, 'image/jpeg');

    @unlink($tempOriginal);
    @unlink($tempThumb);

    return ['original' => $originalKey, 'thumb' => $thumbKey];
}

    public function generateThumbnail(string $sourceFile, string $destFile): void {
        if (!\file_exists($sourceFile)) {
            throw new \RuntimeException('Source file does not exist.');
        }

        $mime = \mime_content_type($sourceFile);
        
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