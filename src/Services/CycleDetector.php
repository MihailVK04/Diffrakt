<?php

declare(strict_types=1);

namespace Diffrakt\Services;

use Diffrakt\Core\Database;

class CycleDetector {
    
    // Максимална дълбочина на влагане (според изискванията на архитектурата)
    private const MAX_DEPTH = 5;

    /**
     * Проверява дали добавянето на нови стъпки към даден pipeline ще създаде 
     * безкраен цикъл ИЛИ ще надвиши максималната дълбочина на влагане.
     *
     * @return bool Връща true, ако има цикъл ИЛИ дълбочината е надвишена (грешка).
     */
    public static function hasCycle(int $targetPipelineId, array $newSteps): bool {
        $db = Database::getInstance();
        
        // Взимаме всички текущи връзки от базата данни
        $allSteps = $db->fetchAll('
            SELECT pipeline_id, sub_pipeline_id 
            FROM pipeline_steps 
            WHERE sub_pipeline_id IS NOT NULL
        ');
        
        // Строим графа
        $graph = [];
        foreach ($allSteps as $row) {
            $pid = (int)$row['pipeline_id'];
            $subId = (int)$row['sub_pipeline_id'];
            $graph[$pid][] = $subId;
        }

        // Симулираме графа с новите стъпки, преди да са записани
        $graph[$targetPipelineId] = [];
        foreach ($newSteps as $step) {
            if (!empty($step['sub_pipeline_id'])) {
                $graph[$targetPipelineId][] = (int)$step['sub_pipeline_id'];
            }
        }

        // Стартираме обхождане (DFS) от всеки възможен възел
        foreach (array_keys($graph) as $node) {
            // ФИКС: Зануляваме историите за ВСЕКИ нов старт, 
            // за да измерим точната максимална дълбочина от този корен!
            $visited = [];
            $recursionStack = [];
            
            if (self::dfs($node, $graph, $visited, $recursionStack, 1)) {
                return true; 
            }
        }

        return false;
    }

    /**
     * Рекурсивна помощна функция за Търсене в дълбочина (DFS).
     */
    private static function dfs(int $node, array &$graph, array &$visited, array &$recursionStack, int $depth): bool {
        // Проверка за лимит на дълбочината
        if ($depth > self::MAX_DEPTH) {
            return true;
        }

        // Ако попаднем на възел, който е в текущия път - имаме безкраен цикъл
        if (!empty($recursionStack[$node])) {
            return true; 
        }

        // За да предотвратим излишно обикаляне на едни и същи клони ПРИ ЕДНО И СЪЩО стартиране
        if (!empty($visited[$node])) {
            return false;
        }

        $visited[$node] = true;
        $recursionStack[$node] = true;

        if (isset($graph[$node])) {
            foreach ($graph[$node] as $child) {
                if (self::dfs($child, $graph, $visited, $recursionStack, $depth + 1)) {
                    return true; 
                }
            }
        }

        $recursionStack[$node] = false;

        return false;
    }
}