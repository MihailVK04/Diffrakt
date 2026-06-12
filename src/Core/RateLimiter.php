<?php

declare(strict_types=1);

namespace Diffrakt\Core;

use PDO;

class RateLimiter {
    
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
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
                requests     = IF(TIMESTAMPDIFF(SECOND, window_start, NOW()) >= :window_a, 1, requests + 1),
                window_start = IF(TIMESTAMPDIFF(SECOND, window_start, NOW()) >= :window_b, NOW(), window_start)
        ';
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'ip_hash'  => $ipHash,
            'endpoint' => $endpoint,
            'window_a' => $windowSeconds,
            'window_b' => $windowSeconds,
        ]);

        $fetch = $this->pdo->prepare(
            'SELECT requests FROM rate_limits WHERE ip_hash = ? AND endpoint = ?'
        );
        $fetch->execute([$ipHash, $endpoint]);
 
        return (int) $fetch->fetchColumn();
    }

    private function clientIp(): string {
        $isProduction = ($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'production';

        if ($isProduction) {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if ($forwarded !== '') {
                return trim(explode(',', $forwarded)[0]);
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private function hashIp(string $ip): string {
        return hash('sha256', $ip);
    }
}
?>