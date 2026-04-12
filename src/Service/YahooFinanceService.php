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

    private function ua(): string
    {
        return "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36";
    }

    private function cookieStringFile(): string
    {
        return $this->cacheDir . 'yahoo-cookie-string.txt';
    }

    private function loadCookies(): string
    {
        $f = $this->cookieStringFile();
        return file_exists($f) ? trim(file_get_contents($f)) : '';
    }

    /**
     * Obtain (and cache for 1 hour) a Yahoo Finance crumb.
     * Requires a cookie string previously saved via saveCookies().
     */
    private function getSession(): array
    {
        $sessionFile = $this->cacheDir . 'yahoo-session.json';

        if (file_exists($sessionFile) && filemtime($sessionFile) > time() - 3600) {
            return json_decode(file_get_contents($sessionFile), true);
        }

        $cookies = $this->loadCookies();
        if ($cookies === '') {
            error_log("[Yahoo] No cookie string on file — upload via the Dividends page");
            return ['crumb' => ''];
        }

        $ch = curl_init('https://query1.finance.yahoo.com/v1/test/getcrumb');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => $this->ua(),
            CURLOPT_COOKIE         => $cookies,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_ENCODING       => '',
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json, text/plain, */*',
                'Accept-Language: en-GB,en-US;q=0.9,en;q=0.8',
                'Referer: https://finance.yahoo.com/',
                'sec-fetch-dest: empty',
                'sec-fetch-mode: cors',
                'sec-fetch-site: same-site',
            ],
        ]);
        $crumb  = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($crumb === false || $status >= 400 || strlen(trim($crumb)) < 3) {
            error_log("[Yahoo] Failed to obtain crumb (HTTP $status): " . substr((string) $crumb, 0, 100));
            return ['crumb' => ''];
        }

        $session = ['crumb' => trim($crumb)];
        file_put_contents($sessionFile, json_encode($session));
        return $session;
    }

    /**
     * Store the cookie string copied from a browser request (the value of the
     * -b / Cookie: header) and clear the cached crumb so the next request
     * fetches a fresh one with the new cookies.
     */
    public function saveCookieJar(string $content): bool
    {
        $content = trim($content);
        if (!preg_match('/\w+=\S+/', $content)) {
            error_log("[Yahoo] Rejected cookie upload — no key=value pairs found");
            return false;
        }

        file_put_contents($this->cookieStringFile(), $content);
        $this->invalidateSession();
        error_log("[Yahoo] Cookie string saved");
        return true;
    }

    private function invalidateSession(): void
    {
        $sessionFile = $this->cacheDir . 'yahoo-session.json';
        if (file_exists($sessionFile)) {
            unlink($sessionFile);
        }
    }

    /**
     * @throws RuntimeException       on connection failure or non-2xx response
     * @throws RuntimeException(429)  on rate limit — callers should propagate this
     */
    private function fetchYahoo(string $url, bool $retry = true): string
    {
        $session = $this->getSession();

        $fullUrl = $session['crumb']
            ? $url . (str_contains($url, '?') ? '&' : '?') . 'crumb=' . rawurlencode($session['crumb'])
            : $url;
error_log("Attempting to get ".$fullUrl);
        $ch = curl_init($fullUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => $this->ua(),
            CURLOPT_COOKIE         => $this->loadCookies(),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_ENCODING       => '',
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json, text/plain, */*',
                'Accept-Language: en-GB,en-US;q=0.9,en;q=0.8',
                'Referer: https://finance.yahoo.com/',
                'sec-fetch-dest: empty',
                'sec-fetch-mode: cors',
                'sec-fetch-site: same-site',
            ],
        ]);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($body === false || $err !== '') {
            error_log("[Yahoo] cURL error for $url: $err");
            throw new RuntimeException("Yahoo Finance connection failed");
        }

        if ($status === 401 && $retry) {
            error_log("[Yahoo] 401 on $url — invalidating session and retrying");
            $this->invalidateSession();
            return $this->fetchYahoo($url, false);
        }

        if ($status === 429) {
            error_log("[Yahoo] Rate limited (429): $url");
            throw new RuntimeException("Yahoo Finance rate limit", 429);
        }

        if ($status >= 400) {
            error_log("[Yahoo] HTTP $status for $url — " . substr($body, 0, 300));
            throw new RuntimeException("Yahoo Finance returned HTTP $status");
        }

        return $body;
    }

    /**
     * Fetch a 6-month weekly OHLC quote for a portfolio stock.
     * Cache TTL: 15 minutes while market is open; indefinite while closed.
     */
    public function getSymbolQuote(string $symbol): StockQuote
    {
        $cacheFile   = $this->cacheDir . $symbol . '-6mo-1wk.json';
        $lastChecked = $this->repo->getSymbolLastChecked($symbol);
        $stale       = ($lastChecked + 900) < time(); // 15 minutes

        $needsFetch = !file_exists($cacheFile) || ($stale && !$this->isMarketClosed());

        if ($needsFetch) {
            $json = $this->fetchYahoo(
                'https://query1.finance.yahoo.com/v8/finance/chart/' . rawurlencode($symbol) . '?interval=1wk&range=6mo'
            );
            file_put_contents($cacheFile, $json);
            $this->repo->upsertSymbolLastChecked($symbol, time());
            $lastChecked = time();
        }

        $data   = json_decode(file_get_contents($cacheFile), true);
        $result = $data['chart']['result'][0];
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
            $meta['symbol'],
            (float) ($meta['regularMarketPrice'] ?? 0),
            (int)   ($meta['regularMarketTime']  ?? 0),
            $lastChecked,
            $tss,
            $ohlc,
        );
    }

    /**
     * Fetch upcoming dividend info for one symbol via the quoteSummary JSON API.
     *
     * Uses calendarEvents + summaryDetail modules in a single call — avoids HTML
     * scraping entirely (which was hitting GDPR cookie walls before the page loaded).
     *
     * Cache rules:
     *  - Known future ex-div date → skip until that date has passed.
     *  - Otherwise → re-check after 24 hours.
     *
     * Returns null when Yahoo returns no dividend data for the symbol.
     */
    public function getUpcomingDividendInfo(string $symbol): ?array
    {
        $cacheFile = $this->cacheDir . $symbol . '-calendarEvents.json';

        if (file_exists($cacheFile)) {
            $cached         = json_decode(file_get_contents($cacheFile), true);
            $exDivTimestamp = (int) ($cached['exDivTimestamp'] ?? 0);

            if ($exDivTimestamp > time()) {
                // Ex-div date still in the future — no need to re-check
                return $cached['data'];
            }

            // Ex-div date passed (or unknown) — honour 24-hour cache
            if (filemtime($cacheFile) > time() - 86400) {
                return $cached['data'];
            }
        }

        $url  = 'https://query1.finance.yahoo.com/v10/finance/quoteSummary/' . rawurlencode($symbol)
              . '?modules=calendarEvents%2CsummaryDetail%2Cprice';
        $json = $this->fetchYahoo($url);

        $data   = json_decode($json, true);
        $result = $data['quoteSummary']['result'][0] ?? null;

        if ($result === null) {
            error_log("[Yahoo] quoteSummary returned no result for $symbol");
            file_put_contents($cacheFile, json_encode(['exDivTimestamp' => 0, 'data' => null]));
            return null;
        }

        // calendarEvents carries the forward-looking ex-div / payment dates
        $cal            = $result['calendarEvents'] ?? [];
        $exDivTimestamp = (int) ($cal['exDividendDate']['raw'] ?? 0);
        $payTimestamp   = (int) ($cal['dividendDate']['raw']   ?? 0);

        // summaryDetail carries the declared dividend rate
        $detail       = $result['summaryDetail'] ?? [];
        $dividendRate = (float) ($detail['dividendRate']['raw'] ?? 0);

        // price module carries the human-readable company name
        $name = (string) ($result['price']['shortName'] ?? '');

        if ($exDivTimestamp <= 0) {
            file_put_contents($cacheFile, json_encode(['exDivTimestamp' => 0, 'data' => null]));
            return null;
        }

        $row = [
            'name'           => $name,
            'exDivDate'      => date('Y-m-d', $exDivTimestamp),
            'exDivTimestamp' => $exDivTimestamp,
            'paymentDate'    => $payTimestamp > 0 ? date('Y-m-d', $payTimestamp) : null,
            'dividendRate'   => $dividendRate > 0 ? $dividendRate : null,
        ];

        file_put_contents($cacheFile, json_encode([
            'exDivTimestamp' => $exDivTimestamp,
            'data'           => $row,
        ]));

        return $row;
    }
}
