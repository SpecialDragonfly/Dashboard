<?php

namespace App\Repository;

use App\Domain\Stock;
use PDO;

class DividendRepository
{
    public function __construct(private readonly PDO $db) {}

    /** @return Stock[] */
    public function getPortfolio(): array
    {
        $sql = <<<SQL
SELECT p.id, p.symbol, p.name, p.quantity, p.price, p."ex-div", p.dividend,
       COALESCE(SUM(d.amount), 0) AS total_dividend_payments
FROM portfolio p
LEFT JOIN dividend_payments d ON p.id = d.symbol_id
WHERE p.deleted = 0
GROUP BY p.id
ORDER BY p.symbol ASC
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return array_map($this->hydrateStock(...), $stmt->fetchAll());
    }

    public function findBySymbol(string $symbol): ?Stock
    {
        $stmt = $this->db->prepare(
            'SELECT id, symbol, name, quantity, price, "ex-div", dividend FROM portfolio WHERE symbol = :symbol AND deleted = 0'
        );
        $stmt->bindValue(':symbol', $symbol);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? $this->hydrateStock($row + ['total_dividend_payments' => 0]) : null;
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
            'UPDATE portfolio SET quantity = :quantity, price = :price, "ex-div" = NULL, dividend = NULL WHERE symbol = :symbol AND deleted = 0'
        );
        $stmt->bindValue(':symbol', $symbol);
        $stmt->bindValue(':quantity', $quantity);
        $stmt->bindValue(':price', $price);
        return $stmt->execute();
    }

    public function updateStockDividendInfo(string $symbol, string $exDiv, string $dividend): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE portfolio SET "ex-div" = :exDiv, dividend = :dividend WHERE symbol = :symbol AND deleted = 0'
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
        $stmt = $this->db->prepare('SELECT "last-checked" FROM symbols WHERE ticker = :ticker');
        $stmt->bindValue(':ticker', $ticker);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? (int) $row['last-checked'] : 0;
    }

    public function upsertSymbolLastChecked(string $ticker, int $time): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO symbols (ticker, "last-checked") VALUES (:ticker, :time)
             ON CONFLICT(ticker) DO UPDATE SET "last-checked" = excluded."last-checked"'
        );
        $stmt->bindValue(':ticker', $ticker);
        $stmt->bindValue(':time', $time);
        $stmt->execute();
    }

    private function hydrateStock(array $row): Stock
    {
        return new Stock(
            (int) $row['id'],
            $row['symbol'],
            $row['name'],
            (float) $row['quantity'],
            (float) $row['price'],
            $row['ex-div'] ?? null,
            $row['dividend'] ?? null,
            (float) ($row['total_dividend_payments'] ?? 0),
        );
    }
}
