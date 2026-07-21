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
     *
     * @return array<int, array{
     *     name: string,
     *     ticker: string,
     *     amount: float|int|string|null,
     *     currency: string,
     *     exDivDate: string,
     *     exDivTimestamp: int
     * }>
     */
    public function getDeclaredDividends(): array
    {
        $cacheFile = $this->cacheDir . 'snowball-declared.json';

        if (file_exists($cacheFile) && filemtime($cacheFile) > time() - self::CACHE_TTL) {
            $cached = file_get_contents($cacheFile);
            if ($cached !== false) {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    return $this->normalizeCachedDividends($decoded);
                }
            }
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

            if (!is_string($body) || $status !== 200) {
                error_log("[Snowball] HTTP $status on page $page");
                break;
            }

            $data = json_decode($body, true);
            $data = is_array($data) ? $data : [];

            $items = isset($data['data']) && is_array($data['data']) ? $data['data'] : [];

            $totalCount = isset($data['totalCount']) && is_numeric($data['totalCount'])
                ? (int)$data['totalCount']
                : 0;
            $pageSize = isset($data['pageSize']) && is_numeric($data['pageSize'])
                ? (int)$data['pageSize']
                : 50;
            $totalPages = (int) ceil($totalCount / max($pageSize, 1));

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $status = $item['status'] ?? '';
                if (!is_string($status) || $status !== 'declared') {
                    continue;
                }

                $ticker = $item['ticker'] ?? '';
                $ticker = is_string($ticker) ? $ticker : '';

                $exDivDate = $item['exDividendDate'] ?? '';
                $exDivDate = is_string($exDivDate) ? $exDivDate : '';

                $ts = $exDivDate !== '' ? (int)strtotime($exDivDate) : 0;

                if ($ticker === '' || $ts <= 0) {
                    continue;
                }

                $name = $item['companyName'] ?? $item['name'] ?? '';
                $name = is_string($name) ? $name : '';

                $amount = $item['perShare'] ?? null;
                if (!is_float($amount) && !is_int($amount) && !is_string($amount)) {
                    $amount = null;
                }

                $currency = $item['currency'] ?? '';
                $currency = is_string($currency) ? $currency : '';

                $results[] = [
                    'name'           => $name,
                    'ticker'         => $ticker,
                    'amount'         => $amount,
                    'currency'       => $currency,
                    'exDivDate'      => $exDivDate,
                    'exDivTimestamp' => $ts,
                ];
            }

            $page++;
        } while ($page <= min($totalPages, self::MAX_PAGES));

        file_put_contents($cacheFile, json_encode($results));
        return $results;
    }

    /**
     * Validate and reshape a previously-cached dividend list (raw decoded JSON)
     * back into the precise shape this service guarantees. The cache file is
     * written by this class itself, but its contents are read back as `mixed`,
     * so each field is checked defensively before use.
     *
     * @param array<mixed> $decoded
     * @return array<int, array{
     *     name: string,
     *     ticker: string,
     *     amount: float|int|string|null,
     *     currency: string,
     *     exDivDate: string,
     *     exDivTimestamp: int
     * }>
     */
    private function normalizeCachedDividends(array $decoded): array
    {
        $results = [];

        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = $item['name'] ?? '';
            $ticker = $item['ticker'] ?? '';
            $currency = $item['currency'] ?? '';
            $exDivDate = $item['exDivDate'] ?? '';
            $exDivTimestamp = $item['exDivTimestamp'] ?? null;
            $amount = $item['amount'] ?? null;

            if (
                !is_string($name)
                || !is_string($ticker)
                || !is_string($currency)
                || !is_string($exDivDate)
                || !is_numeric($exDivTimestamp)
            ) {
                continue;
            }

            if (!is_float($amount) && !is_int($amount) && !is_string($amount) && $amount !== null) {
                $amount = null;
            }

            $results[] = [
                'name'           => $name,
                'ticker'         => $ticker,
                'amount'         => $amount,
                'currency'       => $currency,
                'exDivDate'      => $exDivDate,
                'exDivTimestamp' => (int)$exDivTimestamp,
            ];
        }

        return $results;
    }
}
