<?php

declare(strict_types=1);

namespace Diffrakt\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Database
 *
 * PDO singleton. One connection is created per process and reused everywhere.
 * Instantiation is driven by environment variables so the same code works in
 * XAMPP (local) and the Docker/MySQL-8 production container.
 *
 * Required env vars (defined in .env / Docker CI/CD secrets):
 *   DB_HOST     — hostname or IP (e.g. "127.0.0.1" or "db" in Compose)
 *   DB_PORT     — TCP port (default 3306)
 *   DB_NAME     — schema name (e.g. "diffrakt")
 *   DB_USER     — MySQL user
 *   DB_PASS     — MySQL password
 *   DB_CHARSET  — connection charset (default "utf8mb4")
 *
 * Usage:
 *   $db  = Database::getInstance();
 *   $pdo = $db->getPdo();              // raw PDO for one-off queries
 *   $stmt = $db->query($sql, $params); // prepared + executed in one call
 *   $row  = $db->fetchOne($sql, $params);
 *   $rows = $db->fetchAll($sql, $params);
 *   $id   = $db->insert($sql, $params);
 *   $n    = $db->execute($sql, $params);
 */
final class Database
{
    private static ?self $instance = null;

    private PDO $pdo;

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    /**
     * Private — use getInstance().
     *
     * @throws RuntimeException if a required env var is missing.
     * @throws PDOException     if the connection cannot be established.
     */
    private function __construct()
    {
        $host    = $this->requireEnv('DB_HOST');
        $port    = $_ENV['DB_PORT']    ?? $_SERVER['DB_PORT']    ?? '3306';
        $name    = $this->requireEnv('DB_NAME');
        $user    = $this->requireEnv('DB_USER');
        //$pass    = $this->requireEnv('DB_PASS');
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
            // Keep the connection alive across PHP-FPM restarts in Docker.
            PDO::ATTR_PERSISTENT         => false,
            // MySQL 8 strict mode — consistent with the schema.
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci",
        ];

        $this->pdo = new PDO($dsn, $user, $pass, $options);
    }

    // -------------------------------------------------------------------------
    // Singleton access
    // -------------------------------------------------------------------------

    /**
     * Returns the single shared instance, creating it on first call.
     *
     * @throws RuntimeException on missing env vars.
     * @throws PDOException     on connection failure.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Resets the singleton — used in tests to inject a fresh connection.
     * Not called in production code.
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    // -------------------------------------------------------------------------
    // Raw PDO access
    // -------------------------------------------------------------------------

    /**
     * Returns the underlying PDO object for cases not covered by the helpers
     * (e.g. multi-statement transactions, lastInsertId after a complex flow).
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    // -------------------------------------------------------------------------
    // Query helpers
    // -------------------------------------------------------------------------

    /**
     * Prepares and executes a parameterised statement.
     *
     * @param  string  $sql    SQL with named (:foo) or positional (?) placeholders.
     * @param  array   $params Bound values.  Named: ['foo' => $v].  Positional: [$v1, $v2].
     * @return PDOStatement    Ready-to-fetch statement.
     *
     * @throws PDOException on prepare or execute failure.
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetches a single row, or null if no rows match.
     *
     * @param  string     $sql
     * @param  array      $params
     * @return array|null Associative row, or null.
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Fetches every matching row.
     *
     * @param  string  $sql
     * @param  array   $params
     * @return array   Array of associative rows (empty array if none).
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Executes an INSERT statement and returns the last inserted auto-increment ID.
     *
     * @param  string $sql
     * @param  array  $params
     * @return int    Last insert ID (0 if the table has no auto-increment column).
     */
    public function insert(string $sql, array $params = []): int
    {
        $this->query($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Executes an UPDATE / DELETE / REPLACE and returns the number of affected rows.
     *
     * @param  string $sql
     * @param  array  $params
     * @return int    Number of rows affected.
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    // -------------------------------------------------------------------------
    // Transaction helpers
    // -------------------------------------------------------------------------

    /**
     * Wraps a callable in a transaction.
     *
     * Commits on success, rolls back and re-throws on any exception.
     *
     * Usage:
     *   $db->transaction(function (Database $db) {
     *       $db->execute('INSERT …', […]);
     *       $db->execute('UPDATE …', […]);
     *   });
     *
     * @template T
     * @param  callable(self): T $callback
     * @return T                 Whatever $callback returns.
     *
     * @throws \Throwable on failure (original exception re-thrown after rollback).
     */
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

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Reads an env var from $_ENV or $_SERVER (both are populated depending on
     * the SAPI and whether `variables_order` includes E/S in php.ini).
     *
     * @throws RuntimeException if the variable is not set or is an empty string.
     */
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

    // -------------------------------------------------------------------------
    // Prevent cloning / unserialization of the singleton
    // -------------------------------------------------------------------------

    private function __clone(): void {}

    public function __wakeup(): void
    {
        throw new RuntimeException('Database singleton cannot be unserialized.');
    }
}