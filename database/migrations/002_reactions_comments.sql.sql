CREATE TABLE post_reactions (
    user_id    INT NOT NULL,
    post_id    INT NOT NULL,
    reaction   ENUM('like','dislike') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, post_id),
    FOREIGN KEY (user_id) REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES posts(id)  ON DELETE CASCADE
);

CREATE TABLE comments (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id    INT NOT NULL,
    user_id    INT NOT NULL,
    parent_id  INT UNSIGNED NULL DEFAULT NULL,
    body       TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id)   REFERENCES posts(id)    ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,
    INDEX idx_comments_post_id   (post_id),
    INDEX idx_comments_parent_id (parent_id)
);

CREATE TABLE comment_reactions (
    user_id    INT NOT NULL,
    comment_id INT UNSIGNED NOT NULL,
    reaction   ENUM('like','dislike') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, comment_id),
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
);

ALTER TABLE posts ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 0;

UPDATE filters
SET params_schema = '{"level": {"type": "float", "min": 0, "max": 3}}'
WHERE name = 'saturation';