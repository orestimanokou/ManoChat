CREATE TABLE IF NOT EXISTS security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_username VARCHAR(100) NOT NULL,
    receiver_username VARCHAR(100) NOT NULL,
    message_text TEXT NOT NULL,
    threat_type VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX(sender_username),
    INDEX(receiver_username)
);
