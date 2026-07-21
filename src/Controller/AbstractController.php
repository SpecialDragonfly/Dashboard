<?php

namespace App\Controller;

use DateTimeImmutable;

abstract class AbstractController
{
    /**
     * @return array<string, int>
     */
    protected function baseVars(): array
    {
        $now = new DateTimeImmutable('today');
        $employed = new DateTimeImmutable('2024-05-28');

        $year = (int) $now->format('Y');
        $christmas = new DateTimeImmutable("$year-12-25");
        if ($now > $christmas) {
            $christmas = new DateTimeImmutable(($year + 1) . '-12-25');
        }

        return [
            'daysEmployed'  => (int) $employed->diff($now)->days,
            'christmasDays' => (int) $now->diff($christmas)->days,
        ];
    }
}
