<?php

declare(strict_types=1);

namespace Diffrakt\Core;

use PDO;

/**
 * RateLimiter
 *
 * Enforces per-IP, per-endpoint request rate limits using the `rate_limits`
 * MySQL table. Called by Middleware::rateLimit() before controller logic runs.
 *
 * Algorithm: fixed window counter.
 *   - Each row tracks one (ip_hash, endpoint) pair.
 *   - On every request the window is checked:
 *       • Expired  → reset counter to 1, slide window_start to NOW().
 *       • Active   → increment counter.
 *   - If the counter exceeds $maxRequests within the window,
 *     Response::tooManyRequests() is called and execution stops.
 *
 * rate_limits table DDL (include in your migrations):
 *
 *   CREATE TABLE rate_limits (
 *       ip_hash        CHAR(64)     NOT NULL,          -- SHA-256 hex of client IP
 *       endpoint       VARCHAR(128) NOT NULL,          -- logical name, e.g. 'auth.login'
 *       requests       INT UNSIGNED NOT NULL DEFAULT 1,
 *       window_start   DATETIME     NOT NULL,          -- start of current window
 *       PRIMARY KEY (ip_hash, endpoint)
 *   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 * The composite primary key on (ip_hash, endpoint) makes the upsert O(1) and
 * ensures no duplicate rows can exist even under concurrent requests.
 *
 * A nightly cron or MySQL EVENT can truncate the table to prevent unbounded
 * growth, or you can add a scheduled DELETE WHERE window_start < NOW() - INTERVAL 1 DAY.
 */

class RateLimiter {
    
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public static function create(): self {
        return new self(Database::getInstance()->getPdo());
    }

    public function check(string $endpoint, int $maxRequests = 60, int $windowSeconds = 60): void {
        $ipHash  = $this->hashIp($this->clientIp());
        $current = $this->upsert($ipHash, $endpoint, $windowSeconds);
 
        if ($current > $maxRequests) {
            Response::tooManyRequests("Too many requests. Please try again in {$windowSeconds} seconds.");
        }
    }

    private function upsert(string $ipHash, string $endpoint, int $windowSeconds): int {
        $sql = '
            INSERT INTO rate_limits (ip_hash, endpoint, requests, window_start)
            VALUES (:ip_hash, :endpoint, 1, NOW())
            ON DUPLICATE KEY UPDATE
                requests = IF(TIMESTAMPDIFF(SECOND, window_start, NOW()) >= :window_reset, 1, requests + 1),
                window_start = IF(TIMESTAMPDIFF(SECOND, window_start, NOW()) >= :window_slide, NOW(), window_start)
        ';
 
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'ip_hash' => $ipHash,
            'endpoint' => $endpoint,
            'window_reset' => $windowSeconds,
            'window_slide' => $windowSeconds,
        ]);

        $fetch = $this->pdo->prepare(
            'SELECT requests FROM rate_limits WHERE ip_hash = ? AND endpoint = ?'
        );
        $fetch->execute([$ipHash, $endpoint]);
 
        return (int) $fetch->fetchColumn();
    }

    private function clientIp(): string {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
 
        if ($forwarded !== '') {
            return trim(explode(',', $forwarded)[0]);
        }
 
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private function hashIp(string $ip): string {
        return hash('sha256', $ip);
    }
}
?>