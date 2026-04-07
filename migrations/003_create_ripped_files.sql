CREATE TABLE IF NOT EXISTS ripped_files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    url TEXT NOT NULL UNIQUE,
    video_id TEXT NOT NULL,
    title TEXT,
    thumbnail TEXT,
    path TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
