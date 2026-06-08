<?php

declare(strict_types=1);

namespace Diffrakt\Services;

use Diffrakt\Core\Database;
use Diffrakt\Filters\FilterInterface;
use Diffrakt\Filters\BlurFilter;
use Diffrakt\Filters\GrayscaleFilter;
use Diffrakt\Filters\SepiaFilter;
use Diffrakt\Filters\BrightnessFilter;
use Diffrakt\Filters\ContrastFilter;
use Diffrakt\Filters\SaturationFilter;
use Diffrakt\Filters\HueRotateFilter;
use Diffrakt\Filters\VignetteFilter;
use Diffrakt\Filters\NoiseFilter;
use Diffrakt\Filters\EdgeDetectFilter;
use RuntimeException;
use InvalidArgumentException;
use LogicException;
use GdImage;

class PipelineRunner {
    
    // Ограничение за дълбочина при рекурсивно изпълнение на под-пайплайни
    private const MAX_DEPTH = 5;

    private Database $db;
    private StorageService $storage;

    /**
     * Речник (Map), свързващ ID-то на филтъра от базата данни
     * с конкретния имплементиращ клас (Шаблон Strategy).
     */
    private array $filterMap = [
        1  => BlurFilter::class,
        2  => GrayscaleFilter::class,
        3  => SepiaFilter::class,
        4  => BrightnessFilter::class,
        5  => ContrastFilter::class,
        6  => SaturationFilter::class,
        7  => HueRotateFilter::class,
        8  => VignetteFilter::class,
        9  => NoiseFilter::class,
        10 => EdgeDetectFilter::class,
    ];

    public function __construct(StorageService $storage) {
        $this->db = Database::getInstance();
        $this->storage = $storage;
    }

    /**
     * Главен метод за стартиране на обработката на изображението.
     *
     * @param string $sourceFilePath Относителен път (напр. 'originals/uuid.jpg')
     * @param int $pipelineId ID на пайплайна от базата данни
     * @return string Относителен път до новото обработено изображение
     */
    public function run(string $sourceFilePath, int $pipelineId): string {
        // ЗАЩИТА: Безопасен деструктуриращ explode
        $parts = explode('/', $sourceFilePath, 2);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException("Невалиден формат на път: {$sourceFilePath}");
        }
        [$category, $filename] = $parts;

        $fullPath = $this->storage->getPath($category, $filename);

        if (!file_exists($fullPath)) {
            throw new InvalidArgumentException("Изходният файл не е намерен: {$sourceFilePath}");
        }

        // Зареждане на изображението с вграден Memory Guard
        $image = $this->loadImage($fullPath);

        // Изпълнение на веригата от филтри (предава се по референция)
        $this->executePipeline($image, $pipelineId, 1);

        // Генериране на ново уникално име за папка 'processed'
        $newFilename = $this->storage->generateUuid() . '.jpg';
        $destination = $this->storage->getPath('processed', $newFilename);
        
        $this->storage->ensureDirectory('processed');

        // Запазване на финалния краен резултат
        if (!imagejpeg($image, $destination, 90)) {
            throw new RuntimeException("Неуспешно запазване на обработеното изображение.");
        }

        return 'processed/' . $newFilename;
    }

    /**
     * Рекурсивен метод за последователно обхождане на стъпките.
     */
    private function executePipeline(GdImage &$image, int $pipelineId, int $depth): void {
        if ($depth > self::MAX_DEPTH) {
            throw new RuntimeException("Надвишен лимит за дълбочина на пайплайна по време на изпълнение.");
        }

        // Извличаме стъпките, сортирани правилно по step_order
        $steps = $this->db->fetchAll('
            SELECT filter_id, sub_pipeline_id, params 
            FROM pipeline_steps 
            WHERE pipeline_id = ? 
            ORDER BY step_order ASC
        ', [$pipelineId]);

        foreach ($steps as $step) {
            $params = !empty($step['params']) ? json_decode($step['params'], true) : [];

            if (!empty($step['filter_id'])) {
                // Предаваме референцията на изображението директно към филтъра
                $this->applyFilter($image, (int)$step['filter_id'], $params);
            } elseif (!empty($step['sub_pipeline_id'])) {
                // Навлизане в под-пайплайн (Рекурсия)
                $this->executePipeline($image, (int)$step['sub_pipeline_id'], $depth + 1);
            }
        }
    }

    /**
     * Динамично намира, валидира и извиква конкретния филтър.
     */
    private function applyFilter(GdImage &$image, int $filterId, array $params): void {
        if (!isset($this->filterMap[$filterId])) {
            throw new RuntimeException("Непознат или неимплементиран филтър с ID: {$filterId}");
        }

        $filterClass = $this->filterMap[$filterId];
        $filter = new $filterClass();
        
        // АРХИТЕКТУРНА ЗАЩИТА: Гарантираме спазването на интерфейса
        if (!($filter instanceof FilterInterface)) {
            throw new LogicException("Класът {$filterClass} не имплементира FilterInterface");
        }
        
        $filter->apply($image, $params);
    }

    /**
     * Помощен метод за безопасно зареждане на файл в паметта с Memory Guard защита.
     */
    private function loadImage(string $fullPath): GdImage {
        // МЕМОРИ ГУАРД: Предпазва от Fatal Error (Out of Memory) при огромни резолюции
        $imageSize = getimagesize($fullPath);
        if ($imageSize !== false) {
            $width = $imageSize[0];
            $height = $imageSize[1];
            if ($width * $height > 40_000_000) { 
                throw new InvalidArgumentException('Изображението е с прекалено голяма резолюция и не може да бъде заредено в паметта.');
            }
        }

        // Сигурна проверка на MIME типа чрез фингърпринт
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fullPath);

        $image = match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($fullPath),
            'image/png'  => imagecreatefrompng($fullPath),
            'image/webp' => imagecreatefromwebp($fullPath),
            'image/gif'  => imagecreatefromgif($fullPath),
            default      => false
        };

        if (!$image) {
            throw new RuntimeException("Неуспешно зареждане на изображението за обработка: {$fullPath}");
        }

        return $image;
    }
}