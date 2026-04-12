<?php

namespace App\Service;

use App\Domain\Stock;
use App\Repository\DividendRepository;
use Throwable;

class DividendService
{
    public function __construct(
        private DividendRepository $repo,
        private YahooFinanceService $yahoo,
        private DividendCalendarInterface $calendar,
        private string $freetradeCsvPath,
    ) {}

    public function getPortfolio(): array
    {
        return array_map(fn(Stock $s) => $s->toArray(), $this->repo->getPortfolio());
    }

    public function addStock(string $symbol, string $name, float $quantity, float $price): array
    {
        $existing = $this->repo->findBySymbol($symbol);

        if ($existing !== null) {
            $newQty = $existing->getQuantity() + $quantity;
            $newPrice = (($existing->getPrice() * $existing->getQuantity()) + ($price * $quantity)) / $newQty;
            $this->repo->updateStockQuantityAndPrice($symbol, $newQty, round($newPrice, 4));
            return ['symbol' => $symbol, 'quantity' => $newQty, 'price' => round($newPrice, 4)];
        }

        $stock = $this->repo->insertStock($symbol, $name, $quantity, $price);
        return ['symbol' => $stock->getSymbol(), 'quantity' => $stock->getQuantity(), 'price' => $stock->getPrice()];
    }

    public function updateStock(string $symbol, string $exDiv, string $dividend): bool
    {
        return $this->repo->updateStockDividendInfo($symbol, $exDiv, $dividend);
    }

    public function deleteStock(string $symbol): bool
    {
        return $this->repo->softDeleteStock($symbol);
    }

    public function addPayment(int $symbolId, string $date, int $amount): bool
    {
        return $this->repo->addPayment($symbolId, $date, $amount);
    }

    /** @return array[] StockQuote::toArray() per symbol, or ['symbol' => ..., 'error' => ...] on failure */
    public function getPrices(array $symbols): array
    {
        $results = [];
        foreach ($symbols as $symbol) {
            try {
                $results[] = $this->yahoo->getSymbolQuote($symbol)->toArray();
            } catch (Throwable $e) {
                $results[] = ['symbol' => $symbol, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    private function freetradeSymbols(): array
    {
        $csv = file_get_contents($this->freetradeCsvPath);
        $symbols = array_values(array_filter(array_map('trim', explode(',', $csv)), fn($s) => $s !== ''));
        sort($symbols);
        return $symbols;
    }

    /**
     * Cross-reference declared dividends from the calendar source against the
     * Freetrade universe, returning only those with a future ex-dividend date,
     * sorted ascending by that date.
     *
     * @return array[] Each entry: symbol, name, exDivDate, exDivTimestamp, amount
     */
    public function getUpcomingDividends(): array
    {
        $declared = $this->calendar->getDeclaredDividends();
        $freetrade = array_flip($this->freetradeSymbols());
        $now = time();
        $results = [];

        foreach ($declared as $item) {
            if (!isset($freetrade[$item['ticker']])) {
                continue;
            }
            if ($item['exDivTimestamp'] <= $now) {
                continue;
            }
            $results[] = [
                'symbol'         => $item['ticker'],
                'name'           => $item['name'],
                'exDivDate'      => $item['exDivDate'],
                'exDivTimestamp' => $item['exDivTimestamp'],
                'amount'         => $item['amount'],
                'currency'       => $item['currency'] ?? '',
            ];
        }

        usort($results, fn($a, $b) => $a['exDivTimestamp'] - $b['exDivTimestamp']);
        return $results;
    }
}
