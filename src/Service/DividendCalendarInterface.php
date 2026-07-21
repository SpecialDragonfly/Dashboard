<?php

namespace App\Service;

interface DividendCalendarInterface
{
    /**
     * Return all declared upcoming LSE dividends, normalised to:
     *   ticker, name, exDivDate (Y-m-d), exDivTimestamp (unix), amount
     *
     * @return array<int, array{
     *     name: string,
     *     ticker: string,
     *     amount: int|float|string|null,
     *     currency: string,
     *     exDivDate: string,
     *     exDivTimestamp: int
     * }>
     */
    public function getDeclaredDividends(): array;
}
