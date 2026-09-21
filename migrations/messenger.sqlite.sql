CREATE TABLE IF NOT EXISTS messenger_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    body TEXT NOT NULL,
    headers TEXT NOT NULL,
    queue_name TEXT NOT NULL,
    created_at TEXT NOT NULL,
    available_at TEXT NOT NULL,
    delivered_at TEXT DEFAULT NULL
);
CREATE INDEX IF NOT EXISTS idx_messenger_queue ON messenger_messages (queue_name, available_at, delivered_at);
