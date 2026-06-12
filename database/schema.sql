CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(40) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    avatar_path VARCHAR(255) NULL DEFAULT NULL,
    bio TEXT NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NULL,
    data TEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    original_path VARCHAR(255) NOT NULL,
    thumb_path VARCHAR(255) NOT NULL,
    processed_path VARCHAR(255) NULL DEFAULT NULL,
    caption TEXT NULL,
    is_published BOOLEAN NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE pipelines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE filters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NULL, 
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL, 
    is_public BOOLEAN NOT NULL DEFAULT 0,
    pipeline_id INT NULL,
    params_schema JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (pipeline_id) REFERENCES pipelines(id) ON DELETE SET NULL
);

CREATE TABLE pipeline_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pipeline_id INT NOT NULL,
    step_order INT NOT NULL,
    filter_id INT NULL,
    sub_pipeline_id INT NULL,
    params JSON NOT NULL DEFAULT ('{}'),
    FOREIGN KEY (pipeline_id) REFERENCES pipelines(id) ON DELETE CASCADE,
    FOREIGN KEY (filter_id) REFERENCES filters(id) ON DELETE CASCADE,
    FOREIGN KEY (sub_pipeline_id) REFERENCES pipelines(id) ON DELETE CASCADE,
    CHECK (
        (filter_id IS NOT NULL AND sub_pipeline_id IS NULL) OR
        (filter_id IS NULL AND sub_pipeline_id IS NOT NULL)
    )
);

CREATE TABLE follows (
    follower_id INT NOT NULL,
    followee_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_id, followee_id),
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (followee_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE rate_limits (
    ip_hash CHAR(64) NOT NULL,
    endpoint VARCHAR(128) NOT NULL,
    requests INT UNSIGNED NOT NULL DEFAULT 1,
    window_start DATETIME NOT NULL,
    PRIMARY KEY (ip_hash, endpoint)
);

CREATE TABLE conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_a_id INT NOT NULL,
    user_b_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_conversation_pair (user_a_id, user_b_id),
    FOREIGN KEY (user_a_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_b_id) REFERENCES users(id) ON DELETE CASCADE,
    CHECK (user_a_id < user_b_id)
);
 
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_messages_conversation_id (conversation_id),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);