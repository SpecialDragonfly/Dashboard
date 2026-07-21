<?php

namespace App\Repository;

use App\Domain\Stock;
use PDO;
use PDOException;

class DividendRepository
{
    public function __construct(private readonly PDO $db) {}

    /** @return Stock[] */
    public function getPortfolio(): array
    {
        $sql = <<<SQL
SELECT p.id, p.symbol, p.name, p.quantity, p.price, p.`ex-div`, p.dividend,
       COALESCE(SUM(d.amount), 0) AS total_dividend_payments
FROM portfolio p
LEFT JOIN dividend_payments d ON p.id = d.symbol_id
WHERE p.deleted = 0
GROUP BY p.id
ORDER BY p.symbol ASC
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $stocks = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $stocks[] = $this->hydrateStock($row);
        }
        return $stocks;
    }

    public function findBySymbol(string $symbol): ?Stock
    {
        $stmt = $this->db->prepare(
            'SELECT id, symbol, name, quantity, price, `ex-div`, dividend FROM portfolio WHERE symbol = :symbol AND deleted = 0'
        );
        $stmt->bindValue(':symbol', $symbol);
        $stmt->execute();
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }
        return $this->hydrateStock($row + ['total_dividend_payments' => 0]);
    }

    public function insertStock(string $symbol, string $name, float $quantity, float $price): Stock
    {
        $stmt = $this->db->prepare(
            'INSERT INTO portfolio (symbol, name, quantity, price) VALUES (:symbol, :name, :quantity, :price)'
        );
        $stmt->bindValue(':symbol', $symbol);
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':quantity', $quantity);
        $stmt->bindValue(':price', $price);
        $stmt->execute();
        return new Stock((int) $this->db->lastInsertId(), $symbol, $name, $quantity, $price, null, null, 0.0);
    }

    public function updateStockQuantityAndPrice(string $symbol, float $quantity, float $price): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE portfolio SET quantity = :quantity, price = :price, `ex-div` = NULL, dividend = NULL WHERE symbol = :symbol AND deleted = 0'
        );
        $stmt->bindValue(':symbol', $symbol);
        $stmt->bindValue(':quantity', $quantity);
        $stmt->bindValue(':price', $price);
        return $stmt->execute();
    }

    public function updateStockDividendInfo(string $symbol, string $exDiv, string $dividend): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE portfolio SET `ex-div` = :exDiv, dividend = :dividend WHERE symbol = :symbol AND deleted = 0'
        );
        $stmt->bindValue(':exDiv', $exDiv);
        $stmt->bindValue(':dividend', $dividend);
        $stmt->bindValue(':symbol', $symbol);
        return $stmt->execute();
    }

    public function softDeleteStock(string $symbol): bool
    {
        $stmt = $this->db->prepare('UPDATE portfolio SET deleted = 1 WHERE symbol = :symbol');
        $stmt->bindValue(':symbol', $symbol);
        return $stmt->execute();
    }

    public function addPayment(int $symbolId, string $date, int $amount): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO dividend_payments (symbol_id, date, amount) VALUES (:symbolId, :date, :amount)'
        );
        $stmt->bindValue(':symbolId', $symbolId);
        $stmt->bindValue(':date', $date);
        $stmt->bindValue(':amount', $amount);
        return $stmt->execute();
    }

    public function getSymbolLastChecked(string $ticker): int
    {
        $stmt = $this->db->prepare('SELECT `last-checked` FROM symbols WHERE ticker = :ticker');
        $stmt->bindValue(':ticker', $ticker);
        $stmt->execute();
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return 0;
        }
        return $this->toInt($row['last-checked']);
    }

    public function upsertSymbolLastChecked(string $ticker, int $time): void
    {
        // Plain PDO upsert, portable across SQLite/MySQL: try the insert, and
        // on a unique-constraint hit (ticker already exists) update instead.
        // Avoids SQLite's ON CONFLICT (no MySQL equivalent) and MySQL's
        // ON DUPLICATE KEY UPDATE (no SQLite equivalent).
        $insert = $this->db->prepare('INSERT INTO symbols (ticker, `last-checked`) VALUES (:ticker, :time)');
        $insert->bindValue(':ticker', $ticker);
        $insert->bindValue(':time', $time);

        try {
            $insert->execute();
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $update = $this->db->prepare('UPDATE symbols SET `last-checked` = :time WHERE ticker = :ticker');
            $update->bindValue(':ticker', $ticker);
            $update->bindValue(':time', $time);
            $update->execute();
        }
    }

    /** @return string[] */
    public function getSnapshottedSymbolsForDate(string $date): array
    {
        $stmt = $this->db->prepare('SELECT symbol FROM portfolio_value_history WHERE date = :date');
        $stmt->bindValue(':date', $date);
        $stmt->execute();

        $symbols = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $symbol = $row['symbol'];
            if (is_string($symbol)) {
                $symbols[] = $symbol;
            }
        }
        return $symbols;
    }

    public function insertValueSnapshot(string $date, string $symbol, float $quantity, float $marketPrice): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO portfolio_value_history (date, symbol, quantity, market_price, value)
             VALUES (:date, :symbol, :quantity, :marketPrice, :value)'
        );
        $stmt->bindValue(':date', $date);
        $stmt->bindValue(':symbol', $symbol);
        $stmt->bindValue(':quantity', $quantity);
        $stmt->bindValue(':marketPrice', $marketPrice);
        $stmt->bindValue(':value', $quantity * $marketPrice);

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            // Row for this date+symbol already exists (race with a concurrent
            // snapshot request) — same effect as SQLite's "INSERT OR IGNORE".
        }
    }

    /** @return list<array{date: string, value: float}> */
    public function getGrowthHistory(): array
    {
        $stmt = $this->db->prepare(
            'SELECT date, SUM(value) AS value FROM portfolio_value_history GROUP BY date ORDER BY date ASC'
        );
        $stmt->execute();

        $history = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $history[] = [
                'date' => $this->toString($row['date']),
                'value' => $this->toFloat($row['value']),
            ];
        }
        return $history;
    }

    /** @param array<mixed, mixed> $row */
    private function hydrateStock(array $row): Stock
    {
        return new Stock(
            $this->toInt($row['id']),
            $this->toString($row['symbol']),
            $this->toString($row['name']),
            $this->toFloat($row['quantity']),
            $this->toFloat($row['price']),
            $this->toNullableString($row['ex-div'] ?? null),
            $this->toNullableString($row['dividend'] ?? null),
            $this->toFloat($row['total_dividend_payments'] ?? 0),
        );
    }

    private function toInt(mixed $value): int
    {
        if (is_int($value) || is_float($value) || is_string($value)) {
            return (int) $value;
        }
        return 0;
    }

    private function toFloat(mixed $value): float
    {
        if (is_int($value) || is_float($value) || is_string($value)) {
            return (float) $value;
        }
        return 0.0;
    }

    private function toString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return '';
    }

    private function toNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        return $this->toString($value);
    }
}
