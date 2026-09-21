CREATE TABLE IF NOT EXISTS messenger_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    body LONGTEXT NOT NULL,
    headers LONGTEXT NOT NULL,
    queue_name VARCHAR(190) NOT NULL,
    created_at DATETIME NOT NULL,
    available_at DATETIME NOT NULL,
    delivered_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_messenger_queue (queue_name, available_at, delivered_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
