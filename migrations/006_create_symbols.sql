CREATE TABLE IF NOT EXISTS symbols (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    ticker         TEXT    NOT NULL UNIQUE,
    "last-checked" INTEGER NOT NULL DEFAULT 0
);
