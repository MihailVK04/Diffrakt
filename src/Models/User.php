<?php

declare(strict_types=1);

namespace Diffrakt\Models;

use Diffrakt\Core\Database;

class User {

    public static function findById(int $id): ?array {
        return Database::getInstance()->fetchOne(
            'SELECT id, username, email, avatar_path, bio, created_at FROM users WHERE id = ?',
            [$id]
        );
    }

    public static function findByUsername(string $username): ?array {
        return Database::getInstance()->fetchOne(
            'SELECT id, username, email, password_hash, avatar_path FROM users WHERE username = ?',
            [$username]
        );
    }

    public static function findByEmail(string $email): ?array {
        return Database::getInstance()->fetchOne(
            'SELECT id, username, email, password_hash FROM users WHERE email = ?',
            [$email]
        );
    }

    public static function create(array $data): int {
        return Database::getInstance()->insert(
            'INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :password_hash)',
            [
                'username' => $data['username'],
                'email' => $data['email'],
                'password_hash' => $data['password_hash']
            ]
        );
    }

    public static function updateAvatar(int $id, string $avatarPath): int {
        return Database::getInstance()->execute(
            'UPDATE users SET avatar_path = ? WHERE id = ?',
            [$avatarPath, $id]
        );
    }

    public static function updateEmail(int $id, string $email): int {
        return Database::getInstance()->execute(
            'UPDATE users SET email = ? WHERE id = ?',
            [$email, $id]
        );
    }
}