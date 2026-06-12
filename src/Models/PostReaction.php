<?php
declare(strict_types=1);

namespace Diffrakt\Models;

use Diffrakt\Core\Database;

class PostReaction {
    public static function react(int $userId, int $postId, string $reaction): void {
        Database::getInstance()->execute(
            'INSERT INTO post_reactions (user_id, post_id, reaction) 
             VALUES (?, ?, ?) 
             ON DUPLICATE KEY UPDATE reaction = ?',
            [$userId, $postId, $reaction, $reaction]
        );
    }

    public static function remove(int $userId, int $postId): void {
        Database::getInstance()->execute(
            'DELETE FROM post_reactions WHERE user_id = ? AND post_id = ?',
            [$userId, $postId]
        );
    }
}