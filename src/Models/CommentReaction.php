<?php
declare(strict_types=1);

namespace Diffrakt\Models;

use Diffrakt\Core\Database;

class CommentReaction {
    public static function react(int $userId, int $commentId, string $reaction): void {
        Database::getInstance()->execute(
            'INSERT INTO comment_reactions (user_id, comment_id, reaction) 
             VALUES (?, ?, ?) 
             ON DUPLICATE KEY UPDATE reaction = ?',
            [$userId, $commentId, $reaction, $reaction]
        );
    }

    public static function remove(int $userId, int $commentId): void {
        Database::getInstance()->execute(
            'DELETE FROM comment_reactions WHERE user_id = ? AND comment_id = ?',
            [$userId, $commentId]
        );
    }
}