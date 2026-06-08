<?php

declare(strict_types=1);

namespace Diffrakt\Services;

use Diffrakt\Models\PipelineStep;
use Diffrakt\Models\Filter;

class PipelineRunner {

    private StorageService $storage;

    public function __construct(StorageService $storage) {
        $this->storage = $storage;
    }

    public function run(string $originalPath, int $pipelineId): string {
        $db = \Diffrakt\Core\Database::getInstance();
        $absoluteSource = $_ENV['STORAGE_PATH'] . '/' . $originalPath;
        
        if (!file_exists($absoluteSource)) {
            throw new \RuntimeException('Source file not found.');
        }

        $processedFilename = bin2hex(random_bytes(16)) . '.jpg';
        $absoluteDest = $_ENV['STORAGE_PATH'] . '/processed/' . $processedFilename;

        if (!is_dir(dirname($absoluteDest))) {
            mkdir(dirname($absoluteDest), 0755, true);
        }

        copy($absoluteSource, $absoluteDest);
        $this->runAbsolute($absoluteDest, $pipelineId);

        $relativePath = 'processed/' . $processedFilename;
        $db->execute('UPDATE posts SET processed_path = ? WHERE original_path = ?', [$relativePath, $originalPath]);

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
            $filterDef = Filter::findById((int)$step['filter_id']);
            if (!$filterDef) {
                continue;
            }

            $className = '\\Diffrakt\\Filters\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', $filterDef['name']))) . 'Filter';
            
            if (class_exists($className)) {
                $filterInstance = new $className();
                $image = $filterInstance->apply($image, $step['params']);
            }
        }

        imagejpeg($image, $absolutePath, 90);
        //imagedestroy($image);
    }
}