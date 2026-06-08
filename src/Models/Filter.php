<?php

declare(strict_types=1);

namespace Diffrakt\Models;

use Diffrakt\Core\Database;

class Filter {

    public static function findById(int $id): ?array {
        return Database::getInstance()->fetchOne('SELECT * FROM filters WHERE id = ?', [$id]);
    }

    public static function findAllPublicOrOwned(?int $userId): array {
        $sql = 'SELECT * FROM filters WHERE is_public = 1 OR owner_id = ? ORDER BY type ASC, name ASC';
        return Database::getInstance()->fetchAll($sql, [$userId ?? 0]);
    }

    public static function createComposite(array $data): int {
        return Database::getInstance()->insert(
            'INSERT INTO filters (name, type, owner_id) VALUES (:name, "composite", :owner_id)',
            [
                'name' => $data['name'],
                'owner_id' => $data['owner_id']
            ]
        );
    }

    public static function delete(int $id, int $userId): int {
        return Database::getInstance()->execute(
            'DELETE FROM filters WHERE id = ? AND owner_id = ? AND type = "composite"',
            [$id, $userId]
        );
    }
}