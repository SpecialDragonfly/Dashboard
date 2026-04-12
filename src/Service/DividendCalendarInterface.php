<?php

namespace App\Service;

interface DividendCalendarInterface
{
    /**
     * Return all declared upcoming LSE dividends, normalised to:
     *   ticker, name, exDivDate (Y-m-d), exDivTimestamp (unix), amount
     *
     * @return array[]
     */
    public function getDeclaredDividends(): array;
}
