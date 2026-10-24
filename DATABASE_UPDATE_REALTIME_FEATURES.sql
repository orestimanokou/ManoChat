ALTER TABLE users
ADD COLUMN IF NOT EXISTS last_seen DATETIME NULL DEFAULT NULL;

CREATE TABLE IF NOT EXISTS typing_status (
    typer_username VARCHAR(100) NOT NULL,
    receiver_username VARCHAR(100) NOT NULL,
    is_typing TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (typer_username, receiver_username),
    INDEX idx_receiver_updated (receiver_username, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

UPDATE users
SET last_seen = NOW()
WHERE last_seen IS NULL;
