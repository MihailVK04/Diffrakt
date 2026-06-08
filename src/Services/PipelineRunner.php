<?php
declare(strict_types=1);

namespace Diffrakt\Services;

use Diffrakt\Models\PipelineStep;
use Diffrakt\Models\Filter;

class PipelineRunner {
    private StorageService $storage;
    private array $filterMap = [
        1 => \Diffrakt\Filters\BlurFilter::class,
        2 => \Diffrakt\Filters\GrayscaleFilter::class,
        3 => \Diffrakt\Filters\SepiaFilter::class,
        4 => \Diffrakt\Filters\BrightnessFilter::class,
        5 => \Diffrakt\Filters\ContrastFilter::class,
        6 => \Diffrakt\Filters\SaturationFilter::class,
        7 => \Diffrakt\Filters\HueRotateFilter::class,
        8 => \Diffrakt\Filters\VignetteFilter::class,
        9 => \Diffrakt\Filters\NoiseFilter::class,
        10 => \Diffrakt\Filters\EdgeDetectFilter::class
    ];

    public function __construct(StorageService $storage) {
        $this->storage = $storage;
    }

    public function run(string $originalPath, int $pipelineId): string {
        [$sourceCategory, $sourceFilename] = explode('/', $originalPath, 2);
        $absoluteSource = $this->storage->getPath($sourceCategory, $sourceFilename);
        
        if (!file_exists($absoluteSource)) {
            throw new \RuntimeException('Source file not found.');
        }

        $processedFilename = bin2hex(random_bytes(16)) . '.jpg';
        $relativePath = 'processed/' . $processedFilename;
        $absoluteDest = $this->storage->getPath('processed', $processedFilename);

        if (!is_dir(dirname($absoluteDest))) {
            mkdir(dirname($absoluteDest), 0755, true);
        }

        copy($absoluteSource, $absoluteDest);
        $this->runAbsolute($absoluteDest, $pipelineId);

        return $relativePath;
    }

    public function runAbsolute(string $absolutePath, int $pipelineId): void {
        if (!file_exists($absolutePath)) {
            throw new \RuntimeException('Source file not found.');
        }

        $steps = PipelineStep::getFlattenedSteps($pipelineId);
        if (empty($steps)) {
            return;
        }

        $image = imagecreatefromjpeg($absolutePath);
        if (!$image) {
            throw new \RuntimeException('Failed to load image for processing.');
        }

        foreach ($steps as $step) {
            $filterId = (int)($step['filter_id'] ?? 0);
            
            if (!isset($this->filterMap[$filterId])) {
                continue;
            }

            $filterClass = $this->filterMap[$filterId];
            if (class_exists($filterClass)) {
                $filterInstance = new $filterClass();
                $image = $filterInstance->apply($image, $step['params'] ?? []);
            }
        }

        imagejpeg($image, $absolutePath, 90);
    }
}