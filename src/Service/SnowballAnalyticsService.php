<?php

namespace App\Service;

class SnowballAnalyticsService implements DividendCalendarInterface
{
    private const API_BASE = 'https://snowball-analytics.com/extapi/api/public/dividend-calendar/paged';
    private const CACHE_TTL = 86400; // 24 hours
    private const MAX_PAGES = 20;    // safety cap

    public function __construct(private string $cacheDir)
    {
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
    }

    /**
     * Fetch all declared LSE dividends from Snowball Analytics, paginating until
     * exhausted. Results are cached for 24 hours.
     */
    public function getDeclaredDividends(): array
    {
        $cacheFile = $this->cacheDir . 'snowball-declared.json';

        if (file_exists($cacheFile) && filemtime($cacheFile) > time() - self::CACHE_TTL) {
            return json_decode(file_get_contents($cacheFile), true) ?? [];
        }

        $results = [];
        $page = 1;
        $totalPages = 1;

        do {
            $url = self::API_BASE . '?' . http_build_query(
                [
                    'type'          => 'lse',
                    'dateType'      => 'payment',
                    'pageSize'      => 50,
                    'page'          => $page,
                    'sortBy'        => 'paymentDate',
                    'sortDirection' => 'asc',
                ]
            );

            $ch = curl_init($url);
            curl_setopt_array(
                $ch,
                [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 15,
                    CURLOPT_ENCODING       => '',
                    CURLOPT_HTTPHEADER     => ['Accept: application/json, text/plain, */*'],
                ]
            );
            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($body === false || $status !== 200) {
                error_log("[Snowball] HTTP $status on page $page");
                break;
            }

            $data = json_decode($body, true) ?? [];
            $items = $data['data'] ?? [];
            $totalCount = (int)($data['totalCount'] ?? 0);
            $pageSize = (int)($data['pageSize'] ?? 50);
            $totalPages = (int) ceil($totalCount / max($pageSize, 1));

            foreach ($items as $item) {
                if (($item['status'] ?? '') !== 'declared') {
                    continue;
                }

                $ticker = $item['ticker'] ?? '';
                $exDivDate = $item['exDividendDate'] ?? '';
                $ts = $exDivDate !== '' ? (int)strtotime($exDivDate) : 0;

                if ($ticker === '' || $ts <= 0) {
                    continue;
                }

                $results[] = [
                    'name'           => $item['companyName'] ?? $item['name'] ?? '',
                    'ticker'         => $ticker,
                    'amount'         => $item['perShare'] ?? null,
                    'currency'       => $item['currency'] ?? '',
                    'exDivDate'      => $exDivDate,
                    'exDivTimestamp' => $ts,
                ];
            }

            $page++;
        } while ($page <= min($totalPages, self::MAX_PAGES));

        file_put_contents($cacheFile, json_encode($results));
        return $results;
    }
}
