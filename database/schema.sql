-- ============================================================
-- Diffrakt — schema.sql
-- MySQL 8 · utf8mb4 · strict mode
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ------------------------------------------------------------
-- users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(40)      NOT NULL UNIQUE,
    email         VARCHAR(255)     NOT NULL UNIQUE,
    password_hash VARCHAR(255)     NOT NULL,
    avatar_path   VARCHAR(512)     DEFAULT NULL,
    bio           TEXT             DEFAULT NULL,
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- sessions  (DB-backed PHP session handler — Session.php)
-- Indexed on expires_at за бърз gc() DELETE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sessions (
    id          VARCHAR(128)     NOT NULL PRIMARY KEY,
    user_id     INT UNSIGNED     NOT NULL,
    data        TEXT             NOT NULL,
    expires_at  DATETIME         NOT NULL,
    created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- filters  (atomic = вградени GD; composite = запазен от потребител)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS filters (
    id            INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)     NOT NULL,
    type          ENUM('atomic','composite') NOT NULL DEFAULT 'atomic',
    owner_id      INT UNSIGNED     DEFAULT NULL,
    is_public     TINYINT(1)       NOT NULL DEFAULT 1,
    params_schema JSON             DEFAULT NULL,
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- posts
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS posts (
    id              INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED     NOT NULL,
    original_path   VARCHAR(512)     NOT NULL,
    thumb_path      VARCHAR(512)     NOT NULL,
    processed_path  VARCHAR(512)     DEFAULT NULL,
    caption         TEXT             DEFAULT NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- pipelines
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pipelines (
    id          INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    owner_id    INT UNSIGNED     NOT NULL,
    name        VARCHAR(100)     NOT NULL,
    description TEXT             DEFAULT NULL,
    created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- pipeline_steps
-- Всяка стъпка сочи към filter_id ИЛИ sub_pipeline_id (никога и двете)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pipeline_steps (
    id                INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    pipeline_id       INT UNSIGNED     NOT NULL,
    step_order        TINYINT UNSIGNED NOT NULL,
    filter_id         INT UNSIGNED     DEFAULT NULL,
    sub_pipeline_id   INT UNSIGNED     DEFAULT NULL,
    params            JSON             DEFAULT NULL,
    FOREIGN KEY (pipeline_id)     REFERENCES pipelines(id) ON DELETE CASCADE,
    FOREIGN KEY (filter_id)       REFERENCES filters(id)   ON DELETE SET NULL,
    FOREIGN KEY (sub_pipeline_id) REFERENCES pipelines(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- follows
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS follows (
    follower_id  INT UNSIGNED     NOT NULL,
    followed_id  INT UNSIGNED     NOT NULL,
    created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_id, followed_id),
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (followed_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- rate_limits  (fixed-window counter — RateLimiter.php)
-- PRIMARY KEY на (ip_hash, endpoint) прави upsert O(1)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rate_limits (
    ip_hash      CHAR(64)         NOT NULL,
    endpoint     VARCHAR(128)     NOT NULL,
    requests     INT UNSIGNED     NOT NULL DEFAULT 1,
    window_start DATETIME         NOT NULL,
    PRIMARY KEY (ip_hash, endpoint)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;