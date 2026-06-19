<?php

declare(strict_types=1);

namespace Diffrakt\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

final class Database
{
    private static ?self $instance = null;

    private PDO $pdo;

    private function __construct()
    {
        $host    = $this->requireEnv('DB_HOST');
        $port    = $_ENV['DB_PORT']    ?? $_SERVER['DB_PORT']    ?? '3306';
        $name    = $this->requireEnv('DB_NAME');
        $user    = $this->requireEnv('DB_USER');
        $pass    = $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? '';
        $charset = $_ENV['DB_CHARSET'] ?? $_SERVER['DB_CHARSET'] ?? 'utf8mb4';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $host,
            $port,
            $name,
            $charset,
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci",
        ];

        $this->pdo = new PDO($dsn, $user, $pass, $options);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row !== false ? $row : null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert(string $sql, array $params = []): int
    {
        $this->query($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $callback($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function requireEnv(string $key): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? (getenv($key) ?: null);

        if ($value === null || $value === '') {
            throw new RuntimeException(
                "Database: required environment variable \"{$key}\" is not set. "
                . 'Check your .env file or CI/CD variable definitions.'
            );
        }

        return (string) $value;
    }

    private function __clone(): void {}

    public function __wakeup(): void
    {
        throw new RuntimeException('Database singleton cannot be unserialized.');
    }
}