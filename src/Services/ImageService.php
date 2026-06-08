<?php

declare(strict_types=1);

namespace Diffrakt\Services;

use RuntimeException;
use InvalidArgumentException;
use Throwable;

class ImageService {
    
    // Инжектираме StorageService, за да разделим логиката. 
    // ImageService се занимава само с пиксели, а StorageService - с папки и файлове на диска.
    private StorageService $storage;

    public function __construct(StorageService $storage) {
        $this->storage = $storage;
    }

    /**
     * Главен метод за обработка на качена снимка.
     * Извиква се от контролера, когато потребителят натисне "Upload".
     */
    public function processUpload(array $fileInfo): array {
        // Проверяваме дали файлът изобщо съществува и дали е качен легитимно чрез HTTP POST форма
        if (!isset($fileInfo['tmp_name']) || !is_uploaded_file($fileInfo['tmp_name'])) {
            throw new InvalidArgumentException('Невалиден или липсващ качен файл.');
        }

        // ЗАЩИТА: Не вярваме на разширението на файла (.jpg може да крие вирус).
        // Използваме finfo (File Information), за да прочетем същинското съдържание на файла (MIME тип).
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileInfo['tmp_name']);
        // Бележка за защитата: В PHP 8.5+ не се налага finfo_close(), защото паметта се чисти автоматично.

        // Списък с позволените формати (Whitelist подход за сигурност)
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif'
        ];

        if (!array_key_exists($mimeType, $allowedMimes)) {
            throw new InvalidArgumentException('Невалиден файлов формат. Позволени са само JPG, PNG, WEBP и GIF.');
        }

        // ЗАЩИТА НА ПАМЕТТА (Memory Guard):
        // Ако потребител качи 50-мегапикселова снимка, GD библиотеката ще блокира RAM паметта на сървъра.
        // getimagesize() е много бърза функция, която чете само хедъра на файла, без да го зарежда целия.
        $imageSize = getimagesize($fileInfo['tmp_name']);
        if ($imageSize !== false) {
            $width = $imageSize[0];
            $height = $imageSize[1];
            // 40_000_000 пиксела = около 40 Мегапиксела лимит
            if ($width * $height > 40_000_000) { 
                throw new InvalidArgumentException('Изображението е с прекалено голяма резолюция и не може да бъде обработено.');
            }
        }

        $extension = $allowedMimes[$mimeType];
        $thumbPath = null;

        try {
            // СТЪПКА 1: Генерираме смалената картинка (thumbnail).
            // Правим го ПЪРВО, защото файлът все още се намира във временната папка (tmp_name).
            $thumbPath = $this->generateThumbnail($fileInfo['tmp_name'], $mimeType);

            // СТЪПКА 2: Запазваме оригиналната голяма снимка.
            // Функцията storeUploadedFile ще премести файла, което означава, че tmp_name вече няма да съществува.
            $originalPath = $this->storage->storeUploadedFile($fileInfo, 'originals', $extension);

            // Връщаме пътищата, за да може контролерът да ги запише в базата данни
            return [
                'original' => $originalPath,
                'thumb'    => $thumbPath
            ];
        } catch (Throwable $e) {
            // ПОЧИСТВАНЕ ПРИ ГРЕШКА (Cleanup):
            // Ако записването на оригинала се провали (напр. няма място на диска), 
            // ние вече сме създали thumbnail. За да не остане "сираче", го изтриваме.
            if ($thumbPath !== null) {
                $this->storage->deleteFile($thumbPath);
            }
            throw $e; // Хвърляме грешката обратно към контролера, за да покаже съобщение на потребителя
        }
    }

    /**
     * Създава смалено копие (до 800px) за по-бързо зареждане на фийда.
     */
    private function generateThumbnail(string $sourceFile, string $mimeType): string {
        // Зареждаме снимката в паметта със съответната функция според типа ѝ
        $image = match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($sourceFile),
            'image/png'  => imagecreatefrompng($sourceFile),
            'image/webp' => imagecreatefromwebp($sourceFile),
            'image/gif'  => imagecreatefromgif($sourceFile),
            default      => false
        };

        if (!$image) {
            throw new RuntimeException('Неуспешно зареждане на изображението за генериране на thumbnail.');
        }

        // Взимаме текущите размери
        $width = imagesx($image);
        $height = imagesy($image);

        $newWidth = $width;
        $newHeight = $height;

        // МАТЕМАТИКА ЗА ЗАПАЗВАНЕ НА ПРОПОРЦИИТЕ (Aspect Ratio):
        // Смаляваме снимката само ако е по-голяма от 800 пиксела.
        if ($width > 800 || $height > 800) {
            if ($width > $height) {
                // Ако снимката е хоризонтална (пейзаж)
                $newWidth = 800;
                $newHeight = (int) (($height / $width) * 800);
            } else {
                // Ако снимката е вертикална (портрет)
                $newHeight = 800;
                $newWidth = (int) (($width / $height) * 800);
            }
        }

        // Вградената функция в GD за преоразмеряване
        $thumbImage = imagescale($image, $newWidth, $newHeight);
        
        if (!$thumbImage) {
            throw new RuntimeException('Неуспешно преоразмеряване (scaling) на изображението.');
        }

        // ФИКС ЗА ПРОЗРАЧНОСТ: 
        // Ако файлът е PNG без фон, запазването му като JPEG ще го направи с грозен черен фон.
        // Затова създаваме чисто бяло платно (canvas) и "лепваме" снимката върху него.
        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $thumbImage, 0, 0, 0, 0, $newWidth, $newHeight);

        // Генерираме сигурно UUID име за новия файл
        $filename = $this->storage->generateUuid() . '.jpg';
        $destination = $this->storage->getPath('thumbs', $filename);
        
        // Делегираме на StorageService да се увери, че папката 'thumbs' съществува
        $this->storage->ensureDirectory('thumbs');

        // Записваме финалния резултат върху диска с 85% качество (оптимално за уеб)
        $success = imagejpeg($canvas, $destination, 85);

        // Бележка: В PHP 8.5+ обектите от GD (като $image, $thumbImage, $canvas) 
        // се унищожават автоматично от Garbage Collector-а на PHP, няма нужда от imagedestroy().

        if (!$success) {
            throw new RuntimeException('Неуспешно записване на thumbnail на диска.');
        }

        return 'thumbs/' . $filename;
    }

    /**
     * Подава картинката към браузъра на потребителя по сигурен начин (Стрийминг).
     * Тъй като папката 'storage' е извън публичната директория, никой не може да отвори файла директно.
     */
    public function serveFile(string $category, string $filename): void {
        // getPath проверява за опити за хакване (Path Traversal) и връща точния път
        $fullPath = $this->storage->getPath($category, $filename);

        if (!file_exists($fullPath) || !is_file($fullPath)) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Файлът не е намерен.']);
            exit;
        }

        // Проверяваме истинския тип на файла, преди да го пратим на потребителя
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $contentType = finfo_file($finfo, $fullPath);

        // Изчистваме всякакъв случайно изпратен HTML/текст до момента (Output Buffering).
        // Ако не го направим, картинката може да пристигне "счупена".
        if (ob_get_level()) {
            ob_end_clean();
        }

        // ХЕДЪРИ ЗА СИГУРНОСТ И ПРОИЗВОДИТЕЛНОСТ:
        // 'nosniff' спира браузъра да се опитва да изпълни файла като код, ако е подправен.
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . (string)filesize($fullPath));
        // Казваме на браузъра да запомни снимката за 1 ден (86400 секунди), за да пестим трафик
        header('Cache-Control: public, max-age=86400');

        // Прочитаме файла от диска и го изпращаме директно към потребителя
        readfile($fullPath);
        exit;
    }
}