<?php

namespace App\Service;

use App\Domain\StockQuote;
use App\Repository\DividendRepository;
use RuntimeException;

class YahooFinanceService
{
    public function __construct(
        private string $cacheDir,
        private DividendRepository $repo,
    ) {}

    /**
     * UK market: Mon-Fri roughly 08:00-16:30 London time (UTC+0/+1).
     * Outside these hours prices won't change, so we honour the cache longer.
     */
    private function isMarketClosed(): bool
    {
        $day = (int) date('N'); // 1=Mon … 7=Sun
        if ($day >= 6) {
            return true;
        }
        $minutes = (int) date('G') * 60 + (int) date('i');
        return $minutes < 480 || $minutes > 990; // before 08:00 or after 16:30
    }

    /** Parses the status code out of $http_response_header (set by the http:// stream wrapper). */
    private function parseHttpStatus(array $responseHeaders): ?int
    {
        if (!isset($responseHeaders[0]) || !preg_match('#^HTTP/\S+\s+(\d{3})#', $responseHeaders[0], $m)) {
            return null;
        }
        return (int) $m[1];
    }

    private function logChartCall(string $url, int|string $status): void
    {
        error_log(sprintf('[YahooFinanceService] GET %s -> status=%s', $url, $status));
    }

    /**
     * No-crumb fetch ported from the pre-rebuild app
     * (notquitehuman/src/Dividends/YahooFinanceService.php:63-84). The
     * crumb/cookie session bootstrap this replaced (auto + manual-cookie
     * fallback) turned out to be what was broken under Docker; this plain
     * request is the one that actually works, so it's the only fetch path.
     *
     * @throws RuntimeException on connection failure
     */
    private function fetchChartBody(string $symbol): string
    {
        $yahooSymbol = $symbol . '.L';
        $url = 'https://query1.finance.yahoo.com/v8/finance/chart/'
            . rawurlencode($yahooSymbol) . '?' . http_build_query([
                'symbol'   => $yahooSymbol,
                'interval' => '1wk',
                'range'    => '6mo',
            ]);

        $options = [
            'http' => [
                'method' => 'GET',
                'header' => "Accept-language: en\r\n" .
                    "Cookie: foo=bar\r\n" .
                    "User-Agent: Mozilla/5.0 (iPad; U; CPU OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B334b Safari/531.21.102011-10-16 20:23:10\r\n",
                'ignore_errors' => true,
            ],
        ];
        $context = stream_context_create($options);
        $body   = @file_get_contents($url, false, $context);
        $status = $this->parseHttpStatus($http_response_header ?? []);
        $this->logChartCall($url, $status ?? 'no response (connection failed)');

        if ($body === false) {
            throw new RuntimeException("Yahoo Finance connection failed for $symbol (status: " . ($status ?? 'none') . ')');
        }

        return $body;
    }

    /**
     * Fetch a 6-month weekly OHLC quote for a portfolio stock.
     * Cache TTL: 15 minutes while market is open; indefinite while closed.
     *
     * @throws RuntimeException on connection failure or non-2xx response
     */
    public function getSymbolQuote(string $symbol): StockQuote
    {
        $cacheFile   = $this->cacheDir . $symbol . '-6mo-1wk.json';
        $lastChecked = $this->repo->getSymbolLastChecked($symbol);
        $stale       = ($lastChecked + 900) < time();

        if (!file_exists($cacheFile) || ($stale && !$this->isMarketClosed())) {
            $body = $this->fetchChartBody($symbol);
            file_put_contents($cacheFile, $body);
            $this->repo->upsertSymbolLastChecked($symbol, time());
            $lastChecked = time();
        }

        $data   = json_decode(file_get_contents($cacheFile), true);
        $result = $data['chart']['result'][0] ?? null;

        if ($result === null) {
            @unlink($cacheFile); // don't let a corrupt/empty cache keep poisoning every read
            throw new RuntimeException("Yahoo Finance returned no chart result for $symbol (empty or invalid cache)");
        }

        $meta   = $result['meta'];
        $quote  = $result['indicators']['quote'][0];
        $tss    = $result['timestamp'] ?? [];

        $ohlc = [];
        foreach ($tss as $i => $_) {
            $ohlc[] = [
                round((float) ($quote['open'][$i]  ?? 0), 4),
                round((float) ($quote['high'][$i]  ?? 0), 4),
                round((float) ($quote['low'][$i]   ?? 0), 4),
                round((float) ($quote['close'][$i] ?? 0), 4),
            ];
        }

        return new StockQuote(
            $symbol,
            (float) ($meta['regularMarketPrice'] ?? 0),
            (int) ($meta['regularMarketTime']    ?? 0),
            $lastChecked,
            $tss,
            $ohlc,
        );
    }
}
