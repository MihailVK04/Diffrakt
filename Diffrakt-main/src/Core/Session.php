<?php

declare(strict_types=1);

namespace Diffrakt\Core;

use PDO;
use SessionHandlerInterface;

class Session implements SessionHandlerInterface {

    private PDO $pdo;
    private int $lifetime;

    private function __construct(PDO $pdo, int $lifetime) {
        $this->pdo = $pdo;
        $this->lifetime = $lifetime;
    }

    public static function start(): void {
        $pdo = self::resolvePdo();
        $lifetime = self::resolveLifetime();
        $isSecure = self::isProduction();

        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_secure', $isSecure ? '1' : '0');
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        ini_set('session.use_only_cookies', '1');
        ini_set('session.serialize_handler', 'php');
        
        session_name(self::cookieName());

        $handler = new self($pdo, $lifetime);
        session_set_save_handler($handler, true);

        session_start();
    }

    public function open(string $path, string $name): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read(string $id): string|false {
        $stmt = $this->pdo->prepare('SELECT data FROM sessions WHERE id = ? AND expires_at > NOW()');

        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? (string) $row['data'] : '';
    }

    public function write(string $id, string $data): bool {
        $userId = self::extractUserId($data);

        $stmt = $this->pdo->prepare(
            'INSERT INTO sessions (id, user_id, data, expires_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))
             ON DUPLICATE KEY UPDATE
             user_id = VALUES(user_id),
             data = VALUES(data),
             expires_at = VALUES(expires_at)'
        );

        return $stmt->execute([$id, $userId, $data, $this->lifetime]);
    }

    public function destroy(string $id): bool {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE id = ?');

        return $stmt->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE expires_at < NOW()');

        $stmt->execute();

        return $stmt->rowCount();
    }

    private static function resolvePdo(): PDO
    {
        return Database::getInstance()->getPdo();
    }

    private static function resolveLifetime(): int {
        $raw = $_ENV['SESSION_LIFETIME'] ?? getenv('SESSION_LIFETIME');

        return ($raw !== false && $raw !== '') ? (int) $raw : 7200;
    }

    private static function cookieName(): string
    {
        $raw = $_ENV['SESSION_COOKIE_NAME'] ?? getenv('SESSION_COOKIE_NAME');
 
        return ($raw !== false && $raw !== '')
            ? (string) $raw
            : 'diffrakt_sid';
    }

    private static function isProduction(): bool
    {
        $env = $_ENV['APP_ENV'] ?? getenv('APP_ENV');
 
        return $env === 'production';
    }

    private static function extractUserId(string $data): ?int
    {
        if (preg_match('/user_id\|i:(\d+);/', $data, $matches)) {
            return (int) $matches[1];
        }
 
        return null;
    }
}
?>