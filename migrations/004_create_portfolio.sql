CREATE TABLE IF NOT EXISTS portfolio (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    symbol   TEXT    NOT NULL,
    name     TEXT    NOT NULL,
    quantity REAL    NOT NULL,
    price    REAL    NOT NULL,
    "ex-div" TEXT,
    dividend TEXT,
    deleted  INTEGER NOT NULL DEFAULT 0
);
