<?php
 
declare(strict_types=1);
 
namespace Diffrakt\Models;
 
use Diffrakt\Core\Database;
 
class Message {

    public static function findById(int $id): ?array {
        return Database::getInstance()->fetchOne(
            'SELECT id, conversation_id, sender_id, body, created_at FROM messages WHERE id = ?',
            [$id]
        );
    }

    public static function create(int $conversationId, int $senderId, string $body): int {
        return Database::getInstance()->insert(
            'INSERT INTO messages (conversation_id, sender_id, body) VALUES (:conversation_id, :sender_id, :body)',
            [
                'conversation_id' => $conversationId,
                'sender_id'       => $senderId,
                'body'            => $body,
            ]
        );
    }
 
    public static function getPage(int $conversationId, ?int $beforeId, int $limit): array {
        if ($beforeId !== null) {
            return Database::getInstance()->fetchAll(
                'SELECT id, conversation_id, sender_id, body, created_at
                FROM messages
                WHERE conversation_id = ? AND id < ?
                ORDER BY id DESC
                LIMIT ?',
                [$conversationId, $beforeId, $limit]
            );
        }
 
        return Database::getInstance()->fetchAll(
            'SELECT id, conversation_id, sender_id, body, created_at
            FROM messages
            WHERE conversation_id = ?
            ORDER BY id DESC
            LIMIT ?',
            [$conversationId, $limit]
        );
    }
 
    public static function getAfter(int $conversationId, int $afterId): array {
        return Database::getInstance()->fetchAll(
            'SELECT id, conversation_id, sender_id, body, created_at
            FROM messages
            WHERE conversation_id = ? AND id > ?
            ORDER BY id ASC',
            [$conversationId, $afterId]
        );
    }
}

?>