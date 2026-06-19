<?php
declare(strict_types=1);

namespace Diffrakt\Services;

use Diffrakt\Core\Database;

class CycleDetector {
    public static function hasCycle(int $targetPipelineId, array $newSteps): bool {
        $db = Database::getInstance();
        $rows = $db->fetchAll('SELECT pipeline_id, sub_pipeline_id FROM pipeline_steps WHERE sub_pipeline_id IS NOT NULL');
        
        $graph = [];
        foreach ($rows as $row) {
            $graph[(int)$row['pipeline_id']][] = (int)$row['sub_pipeline_id'];
        }

        $graph[$targetPipelineId] = [];
        foreach ($newSteps as $step) {
            if (!empty($step['sub_pipeline_id'])) {
                $graph[$targetPipelineId][] = (int)$step['sub_pipeline_id'];
            }
        }

        $visited = [];
        $recursionStack = [];
        
        return self::dfs($targetPipelineId, $graph, $visited, $recursionStack, 1);
    }

    private static function dfs(int $node, array $graph, array &$visited, array &$recursionStack, int $depth): bool {
        if ($depth > 5) return true;
        if (isset($recursionStack[$node]) && $recursionStack[$node]) return true;
        if (isset($visited[$node]) && $visited[$node]) return false;

        $visited[$node] = true;
        $recursionStack[$node] = true;

        if (isset($graph[$node])) {
            foreach ($graph[$node] as $neighbor) {
                if (self::dfs($neighbor, $graph, $visited, $recursionStack, $depth + 1)) {
                    return true;
                }
            }
        }

        $recursionStack[$node] = false;
        return false;
    }
}