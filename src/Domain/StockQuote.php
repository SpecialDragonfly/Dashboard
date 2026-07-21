<?php

namespace App\Domain;

class StockQuote
{
    /**
     * @param list<int> $timestamps Unix timestamps (seconds) per candle
     * @param list<array{0: float, 1: float, 2: float, 3: float}> $ohlc Each entry: [open, high, low, close] (floats, in pence)
     */
    public function __construct(
        private string $symbol,
        private float $price,
        private int $priceTime,    // Unix timestamp from Yahoo meta.regularMarketTime
        private int $lastChecked,  // Unix timestamp we last polled Yahoo
        private array $timestamps, // Unix timestamps (seconds) per candle
        private array $ohlc,       // [[o, h, l, c], ...]
    ) {}

    public function getPrice(): float { return $this->price; }
    public function getPriceTime(): int { return $this->priceTime; }
    public function getLastChecked(): int { return $this->lastChecked; }

    /**
     * @return array{
     *     symbol: string,
     *     price: float,
     *     priceTime: int,
     *     lastChecked: int,
     *     timestamps: list<int>,
     *     ohlc: list<array{0: float, 1: float, 2: float, 3: float}>,
     * }
     */
    public function toArray(): array
    {
        return [
            'symbol'      => $this->symbol,
            'price'       => $this->price,
            'priceTime'   => $this->priceTime,
            'lastChecked' => $this->lastChecked,
            'timestamps'  => $this->timestamps,
            'ohlc'        => $this->ohlc,
        ];
    }
}
