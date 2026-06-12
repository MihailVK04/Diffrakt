<?php
declare(strict_types=1);

namespace Diffrakt\Models;

use Diffrakt\Core\Database;

class Comment {
    public static function create(int $postId, int $userId, string $body, ?int $parentId): int {
        return Database::getInstance()->insert(
            'INSERT INTO comments (post_id, user_id, parent_id, body) VALUES (?, ?, ?, ?)',
            [$postId, $userId, $parentId, $body]
        );
    }

    public static function findById(int $id): ?array {
        $comment = Database::getInstance()->fetchOne('SELECT * FROM comments WHERE id = ?', [$id]);
        return $comment ?: null;
    }

    public static function update(int $id, string $body): bool {
        $db = Database::getInstance();
        $db->execute('UPDATE comments SET body = ? WHERE id = ?', [$body, $id]);
        return true;
    }

    public static function delete(int $id): bool {
        Database::getInstance()->execute('DELETE FROM comments WHERE id = ?', [$id]);
        return true;
    }

    public static function findByPost(int $postId, ?int $cursor, int $limit, ?int $userId = null): array {
        $db = Database::getInstance();
        $params = [$userId ?? 0, $postId];
        $cursorQuery = '';
        
        if ($cursor !== null) {
            $cursorQuery = ' AND c.id > ?';
            $params[] = $cursor;
        }
        $params[] = $limit;

        return $db->fetchAll("
            SELECT c.*, u.username, u.avatar_path,
                (SELECT COUNT(*) FROM comment_reactions WHERE comment_id = c.id AND reaction = 'like') AS like_count,
                (SELECT reaction FROM comment_reactions WHERE comment_id = c.id AND user_id = ?) AS user_reaction,
                (SELECT COUNT(*) FROM comments r WHERE r.parent_id = c.id) AS reply_count
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.post_id = ? AND c.parent_id IS NULL $cursorQuery
            ORDER BY c.id ASC
            LIMIT ?
        ", $params);
    }

    public static function findReplies(int $parentId, ?int $userId = null, int $limit = 50): array {
        $db = Database::getInstance();
        return $db->fetchAll("
            SELECT c.*, u.username, u.avatar_path,
                (SELECT COUNT(*) FROM comment_reactions WHERE comment_id = c.id AND reaction = 'like') AS like_count,
                (SELECT reaction FROM comment_reactions WHERE comment_id = c.id AND user_id = ?) AS user_reaction
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.parent_id = ?
            ORDER BY c.id ASC
            LIMIT ?
        ", [$userId ?? 0, $parentId, $limit]);
    }
    
    public static function getReactionCounts(int $id, ?int $userId): array {
        $db = Database::getInstance();
        $row = $db->fetchOne("
            SELECT 
                (SELECT COUNT(*) FROM comment_reactions WHERE comment_id = ? AND reaction = 'like') AS like_count,
                (SELECT reaction FROM comment_reactions WHERE comment_id = ? AND user_id = ?) AS user_reaction
        ", [$id, $id, $id, $userId ?? 0]);
        
        return [
            'like_count' => (int)($row['like_count'] ?? 0),
            'user_reaction' => $row['user_reaction'] ?? null
        ];
    }
    
    public static function getCommentWithDetails(int $commentId, ?int $userId): ?array {
         $db = Database::getInstance();
         $row = $db->fetchOne("
            SELECT c.*, u.username, u.avatar_path,
                (SELECT COUNT(*) FROM comment_reactions WHERE comment_id = c.id AND reaction = 'like') AS like_count,
                (SELECT reaction FROM comment_reactions WHERE comment_id = c.id AND user_id = ?) AS user_reaction,
                (SELECT COUNT(*) FROM comments r WHERE r.parent_id = c.id) AS reply_count
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.id = ?
        ", [$userId ?? 0, $commentId]);
        return $row ?: null;
    }
}