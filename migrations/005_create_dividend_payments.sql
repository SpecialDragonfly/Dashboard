CREATE TABLE IF NOT EXISTS dividend_payments (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    symbol_id INTEGER NOT NULL,
    date      TEXT    NOT NULL,
    amount    INTEGER NOT NULL
);
