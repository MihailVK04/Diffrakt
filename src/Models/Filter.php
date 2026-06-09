<?php
declare(strict_types=1);

namespace Diffrakt\Models;

use Diffrakt\Core\Database;

class Filter {
    public static function createComposite(int $ownerId, string $name, int $pipelineId): int {
        $db = Database::getInstance();
        return $db->insert(
            'INSERT INTO filters (owner_id, name, type, is_public, pipeline_id) VALUES (?, ?, ?, ?, ?)',
            [$ownerId, $name, 'composite', 0, $pipelineId]
        );
    }

    public static function findById(int $id): ?array {
        $db = Database::getInstance();
        $filter = $db->fetchOne('SELECT * FROM filters WHERE id = ?', [$id]);
        if ($filter && is_string($filter['params_schema'])) {
            $filter['params_schema'] = json_decode($filter['params_schema'], true);
        }
        return $filter ?: null;
    }

    public static function findAllPublicOrOwned(int $userId): array {
        $db = Database::getInstance();
        return $db->fetchAll(
            'SELECT * FROM filters WHERE is_public = 1 OR owner_id = ? ORDER BY id ASC',
            [$userId]
        );
    }

    public static function delete(int $id, int $userId): int {
        $db = Database::getInstance();
        return $db->execute(
            'DELETE FROM filters WHERE id = ? AND owner_id = ? AND type = "composite"',
            [$id, $userId]
        );
    }
}