<?php

declare(strict_types=1);

namespace Diffrakt\Models;

use Diffrakt\Core\Database;
use RuntimeException; 

class PipelineStep {

    public static function findByPipelineId(int $pipelineId): array {
        $db = Database::getInstance();
        return $db->fetchAll('SELECT * FROM pipeline_steps WHERE pipeline_id = ? ORDER BY step_order ASC', [$pipelineId]);
    }

    public static function replaceSteps(int $pipelineId, array $steps): void {
        $db = Database::getInstance();
        $db->transaction(function ($db) use ($pipelineId, $steps) {
            $db->execute('DELETE FROM pipeline_steps WHERE pipeline_id = ?', [$pipelineId]);
            $order = 1;
            foreach ($steps as $step) {
                $filterId = $step['filter_id'] ?? null;
                $subId = $step['sub_pipeline_id'] ?? null;
                $params = isset($step['params']) ? json_encode($step['params']) : '{}';

                $db->execute(
                    'INSERT INTO pipeline_steps (pipeline_id, step_order, filter_id, sub_pipeline_id, params) VALUES (?, ?, ?, ?, ?)',
                    [$pipelineId, $order++, $filterId, $subId, $params]
                );
            }
        });
    }

    public static function getFlattenedSteps(int $pipelineId, int $depth = 1): array {
        if ($depth > 5) {
            throw new RuntimeException('Pipeline exceeds maximum nesting depth of 5.');
        }

        $steps = self::findByPipelineId($pipelineId);
        $flattened = [];

        foreach ($steps as $step) {
            $params = is_string($step['params']) ? json_decode($step['params'], true) : ($step['params'] ?? []);

            if (!empty($step['filter_id'])) {
                $flattened[] = [
                    'filter_id' => (int)$step['filter_id'],
                    'sub_pipeline_id' => null,
                    'params' => $params
                ];
            } elseif (!empty($step['sub_pipeline_id'])) {
                $subSteps = self::getFlattenedSteps((int)$step['sub_pipeline_id'], $depth + 1);
                foreach ($subSteps as $subStep) {
                    $flattened[] = $subStep;
                }
            }
        }

        return $flattened;
    }
}