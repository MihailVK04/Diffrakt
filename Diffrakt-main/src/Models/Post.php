<?php
declare(strict_types=1);

namespace Diffrakt\Models;

use Diffrakt\Core\Database;

class Post {
    public static function create(array $data): int {
        $db = Database::getInstance();
        return $db->insert(
            'INSERT INTO posts (user_id, original_path, thumb_path, caption) VALUES (?, ?, ?, ?)',
            [$data['user_id'], $data['original_path'], $data['thumb_path'], $data['caption']]
        );
    }

    public static function findById(int $id): ?array {
        $db = Database::getInstance();
        $post = $db->fetchOne('SELECT * FROM posts WHERE id = ?', [$id]);
        return $post ?: null;
    }

    public static function delete(int $id, int $userId): int {
        $db = Database::getInstance();
        return $db->execute('DELETE FROM posts WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    public static function getFeed(int $userId, ?int $cursorId = null, int $limit = 10, string $scope = 'following'): array {
        $db = Database::getInstance();
        $params = [];
        $cursorQuery = '';

        if ($scope === 'all') {
            $joinAndWhere = '
                LEFT JOIN follows f ON f.followee_id = p.user_id AND f.follower_id = ?
                WHERE (p.user_id = ? OR f.follower_id IS NOT NULL) AND p.is_published = 1
            ';
            $params[] = $userId;
            $params[] = $userId;
        } else {
            $joinAndWhere = '
                JOIN follows f ON f.followee_id = p.user_id
                WHERE f.follower_id = ? AND p.is_published = 1
            ';
            $params[] = $userId;
        }

        if ($cursorId !== null) {
            $cursorQuery = ' AND p.id < ?';
            $params[] = $cursorId;
        }
        $params[] = $limit;

        return $db->fetchAll("
            SELECT p.*, u.username, u.avatar_path
            FROM posts p
            JOIN users u ON p.user_id = u.id
            $joinAndWhere
            $cursorQuery
            ORDER BY p.id DESC
            LIMIT ?
        ", $params);
    }

    public static function getUserPosts(int $userId, ?int $cursorId = null, int $limit = 10): array {
        $db = Database::getInstance();
        $params = [$userId];
        $cursorQuery = '';
        
        if ($cursorId !== null) {
            $cursorQuery = ' AND id < ?';
            $params[] = $cursorId;
        }
        $params[] = $limit;
        
        return $db->fetchAll("
            SELECT id, user_id, original_path, thumb_path, processed_path, caption, created_at
            FROM posts
            WHERE user_id = ? $cursorQuery
            ORDER BY id DESC
            LIMIT ?
        ", $params);
    }

    public static function publish(int $id, int $userId): void {
        Database::getInstance()->execute(
            'UPDATE posts SET is_published = 1 WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
    }
}