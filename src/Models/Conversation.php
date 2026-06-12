<?php
 
declare(strict_types=1);
 
namespace Diffrakt\Models;
 
use Diffrakt\Core\Database;
 
class Conversation {

    public static function findById(int $id): ?array {
        return Database::getInstance()->fetchOne(
            'SELECT id, user_a_id, user_b_id, created_at FROM conversations WHERE id = ?',
            [$id]
        );
    }

    public static function findByPair(int $userAId, int $userBId): ?array {
        [$a, $b] = self::normalisePair($userAId, $userBId);
 
        return Database::getInstance()->fetchOne(
            'SELECT id, user_a_id, user_b_id, created_at FROM conversations WHERE user_a_id = ? AND user_b_id = ?',
            [$a, $b]
        );
    }

    public static function create(int $userAId, int $userBId): int {
        [$a, $b] = self::normalisePair($userAId, $userBId);
 
        return Database::getInstance()->insert(
            'INSERT INTO conversations (user_a_id, user_b_id) VALUES (:user_a_id, :user_b_id)',
            [
                'user_a_id' => $a,
                'user_b_id' => $b,
            ]
        );
    }
 
    public static function listForUser(int $userId): array {
        return Database::getInstance()->fetchAll(
            'SELECT
                c.id,
                c.created_at,
                other.id         AS other_user_id,
                other.username   AS other_username,
                other.avatar_path AS other_avatar_path,
                last_msg.body    AS last_message_body,
                last_msg.created_at AS last_message_at
            FROM conversations c
            JOIN users other ON other.id = IF(c.user_a_id = :uid1, c.user_b_id, c.user_a_id)
            LEFT JOIN messages last_msg ON last_msg.id = (
                SELECT id FROM messages
                WHERE conversation_id = c.id
                ORDER BY id DESC
                LIMIT 1
            )
            WHERE c.user_a_id = :uid2 OR c.user_b_id = :uid3
            ORDER BY COALESCE(last_msg.created_at, c.created_at) DESC',
            [
                'uid1' => $userId,
                'uid2' => $userId,
                'uid3' => $userId,
            ]
        );
    }
 
    public static function isMutualFollow(int $userAId, int $userBId): bool {
        $row = Database::getInstance()->fetchOne(
            'SELECT COUNT(*) AS cnt FROM follows
            WHERE (follower_id = :a1 AND followee_id = :b1)
               OR (follower_id = :b2 AND followee_id = :a2)',
            [
                'a1' => $userAId,
                'b1' => $userBId,
                'b2' => $userBId,
                'a2' => $userAId,
            ]
        );
 
        return isset($row['cnt']) && (int) $row['cnt'] === 2;
    }
 
    private static function normalisePair(int $a, int $b): array {
        return [$a < $b ? $a : $b, $a < $b ? $b : $a];
    }
}

?>