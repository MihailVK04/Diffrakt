<?php

declare(strict_types=1);

namespace Diffrakt\Models;

use Diffrakt\Core\Database;

class Pipeline {

    public static function findById(int $id): ?array {
        return Database::getInstance()->fetchOne('SELECT * FROM pipelines WHERE id = ?', [$id]);
    }

    public static function create(int $userId, string $name, string $description = ''): int {
        return Database::getInstance()->insert(
            'INSERT INTO pipelines (owner_id, name, description) VALUES (?, ?, ?)',
            [$userId, $name, $description]
        );
    }

    public static function delete(int $id, int $userId): int {
        return Database::getInstance()->execute(
            'DELETE FROM pipelines WHERE id = ? AND owner_id = ?',
            [$id, $userId]
        );
    }
}