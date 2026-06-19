<?php

declare(strict_types=1);

namespace Diffrakt\Core;

class Middleware {

    private RateLimiter $rateLimiter;

    public function __construct(RateLimiter $rateLimiter) {
        $this->rateLimiter = $rateLimiter;
    }

    public function requireAuth(): void {
        if (empty($_SESSION['user_id'])) {
            Response::unauthorized('Authentication required.');
        }
    }

    public function rateLimit(string $endpoint, int $maxRequests = 60, int $windowSeconds = 60): void {
        $this->rateLimiter->check($endpoint, $maxRequests, $windowSeconds);
    }

    public function requireAuthAndRateLimit(string $endpoint, int $maxRequests = 60, int $windowSeconds = 60): void {
        $this->requireAuth();
        $this->rateLimit($endpoint, $maxRequests, $windowSeconds);
    }
}
?>