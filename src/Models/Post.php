<?php

declare(strict_types=1);

namespace Diffrakt\Models;

use Diffrakt\Core\Database;

class Post {

    public static function findById(int $id): ?array {
        return Database::getInstance()->fetchOne(
            'SELECT p.*, u.username, u.avatar_path 
             FROM posts p 
             INNER JOIN users u ON p.user_id = u.id 
             WHERE p.id = ?',
            [$id]
        );
    }

    public static function create(array $data): int {
        return Database::getInstance()->insert(
            'INSERT INTO posts (user_id, original_path, thumb_path, caption) 
             VALUES (:user_id, :original_path, :thumb_path, :caption)',
            [
                'user_id' => $data['user_id'],
                'original_path' => $data['original_path'],
                'thumb_path' => $data['thumb_path'],
                'caption' => $data['caption'] ?? ''
            ]
        );
    }

    public static function delete(int $id, int $userId): int {
        return Database::getInstance()->execute(
            'DELETE FROM posts WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
    }

    public static function getFeed(int $userId, int $limit = 50): array {
        $sql = '
            SELECT p.id, p.thumb_path, p.caption, p.created_at, u.username, u.avatar_path
            FROM posts p
            INNER JOIN users u ON p.user_id = u.id
            INNER JOIN follows f ON f.followed_id = u.id
            WHERE f.follower_id = :user_id
            ORDER BY p.id DESC
            LIMIT :limit
        ';

        return Database::getInstance()->fetchAll($sql, ['user_id' => $userId, 'limit' => (int)$limit]);
    }
}