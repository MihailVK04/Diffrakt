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

    public static function findById(int $id, ?int $userId = null): ?array {
        $db = Database::getInstance();
        $post = $db->fetchOne("
            SELECT p.*,
                (SELECT COUNT(*) FROM post_reactions WHERE post_id = p.id AND reaction = 'like') AS like_count,
                (SELECT COUNT(*) FROM comments WHERE post_id = p.id) AS comment_count,
                (SELECT reaction FROM post_reactions WHERE post_id = p.id AND user_id = ?) AS user_reaction
            FROM posts p
            WHERE p.id = ?
        ", [$userId ?? 0, $id]);
        return $post ?: null;
    }

    public static function delete(int $id, int $userId): int {
        $db = Database::getInstance();
        return $db->execute('DELETE FROM posts WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    public static function getFeed(int $userId, ?int $cursorId = null, int $limit = 10): array {
        $db = Database::getInstance();
        $params = [$userId, $userId];
        $cursorQuery = '';
        
        if ($cursorId !== null) {
            $cursorQuery = ' AND p.id < ?';
            $params[] = $cursorId;
        }
        $params[] = $limit;
        
        return $db->fetchAll("
            SELECT p.*, u.username, u.avatar_path,
                (SELECT COUNT(*) FROM post_reactions WHERE post_id = p.id AND reaction = 'like') AS like_count,
                (SELECT COUNT(*) FROM comments WHERE post_id = p.id) AS comment_count,
                (SELECT reaction FROM post_reactions WHERE post_id = p.id AND user_id = ?) AS user_reaction
            FROM posts p
            JOIN users u ON p.user_id = u.id
            JOIN follows f ON f.followee_id = p.user_id
            WHERE f.follower_id = ? AND p.is_published = 1 $cursorQuery
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
            SELECT id, user_id, original_path, thumb_path, processed_path, caption, created_at,
                (SELECT COUNT(*) FROM post_reactions WHERE post_id = posts.id AND reaction = 'like') AS like_count,
                (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) AS comment_count
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