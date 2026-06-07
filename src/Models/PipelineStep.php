<?php

declare(strict_types=1);

namespace Diffrakt\Models;

use Diffrakt\Core\Database;

class PipelineStep {

    public static function findByPipelineId(int $pipelineId): array {
        return Database::getInstance()->fetchAll(
            'SELECT * FROM pipeline_steps WHERE pipeline_id = ? ORDER BY step_order ASC',
            [$pipelineId]
        );
    }

    public static function replaceSteps(int $pipelineId, array $steps): void {
        $db = Database::getInstance();
        
       
        foreach ($steps as $index => $step) {
            $db->execute(
                'INSERT INTO pipeline_steps (pipeline_id, step_order, filter_id, sub_pipeline_id, params) 
                 VALUES (?, ?, ?, ?, ?)',
                [
                    $pipelineId,
                    $index,
                    $step['filter_id'] ?? null,
                    $step['sub_pipeline_id'] ?? null,
                    isset($step['params']) ? json_encode($step['params']) : null
                ]
            );
        }
    }
}