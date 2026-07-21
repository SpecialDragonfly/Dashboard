<?php

namespace App\Service;

use App\Domain\StockQuote;
use App\Repository\DividendRepository;
use RuntimeException;

class YahooFinanceService
{
    private const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36';

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

    /**
     * Store a cookie string copied from a browser's Yahoo Finance session
     * (the value of the request's Cookie: header). Used as a manual fallback
     * when the automatic session bootstrap (ensureAutoCrumb) fails — e.g. if
     * Yahoo starts gating fc.yahoo.com behind its EU consent flow, which
     * plain HTTP requests can't complete.
     */
    public function saveCookieJar(string $content): bool
    {
        $content = trim($content);
        if (!preg_match('/\w+=\S+/', $content)) {
            return false;
        }

        file_put_contents($this->manualCookieFile(), $content);
        @unlink($this->manualCrumbFile()); // force a fresh crumb for the new cookie
        return true;
    }

    private function manualCookieFile(): string
    {
        return $this->cacheDir . 'yahoo-cookie-string.txt';
    }

    private function manualCrumbFile(): string
    {
        return $this->cacheDir . 'yahoo-manual-crumb.txt';
    }

    private function loadManualCookieString(): string
    {
        $f = $this->manualCookieFile();
        return file_exists($f) ? trim(file_get_contents($f)) : '';
    }

    private function isValidCrumb(string|false $crumb, int $status): bool
    {
        return $crumb !== false
            && $status < 400
            && trim($crumb) !== ''
            && !str_contains($crumb, '<html')
            && !str_contains($crumb, 'Too Many Requests');
    }

    /** @return array{0: string|false, 1: int} [crumb body, http status] */
    private function requestCrumb(array $cookieOpt): array
    {
        $ch = curl_init('https://query1.finance.yahoo.com/v1/test/getcrumb');
        curl_setopt_array($ch, $cookieOpt + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => self::USER_AGENT,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json, text/plain, */*',
                'Accept-Language: en-GB,en-US;q=0.9,en;q=0.8',
                'Referer: https://finance.yahoo.com/',
            ],
        ]);
        $crumb  = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$crumb, $status];
    }

    /** @return array{0: string|false, 1: int, 2: string} [body, status, curl error] */
    private function requestChart(string $symbol, string $crumb, array $cookieOpt): array
    {
        $url = 'https://query2.finance.yahoo.com/v8/finance/chart/'
            . rawurlencode($symbol) . '?interval=1wk&range=6mo&crumb=' . rawurlencode($crumb);

        $ch = curl_init($url);
        curl_setopt_array($ch, $cookieOpt + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => self::USER_AGENT,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json, text/plain, */*',
                'Accept-Language: en-GB,en-US;q=0.9,en;q=0.8',
                'Referer: https://finance.yahoo.com/',
            ],
        ]);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        return [$body, $status, $err];
    }

    /**
     * Automatic session bootstrap: GET fc.yahoo.com for a cookie, then
     * GET /v1/test/getcrumb for the crumb — both plain HTTP, no JS involved.
     * Persisted to the cache dir so every subsequent symbol lookup reuses the
     * same session instead of re-bootstrapping per request.
     *
     * @throws RuntimeException if the bootstrap itself fails
     */
    private function ensureAutoCrumb(string $cookieFile, string $crumbFile): string
    {
        if (file_exists($cookieFile) && file_exists($crumbFile)) {
            return file_get_contents($crumbFile);
        }

        $ch = curl_init('https://fc.yahoo.com');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEJAR      => $cookieFile,
            CURLOPT_COOKIEFILE     => $cookieFile,
            CURLOPT_USERAGENT      => self::USER_AGENT,
        ]);
        curl_exec($ch);
        curl_close($ch);

        [$crumb, $status] = $this->requestCrumb([CURLOPT_COOKIEJAR => $cookieFile, CURLOPT_COOKIEFILE => $cookieFile]);

        if (!$this->isValidCrumb($crumb, $status)) {
            throw new RuntimeException("Failed to obtain Yahoo Finance session crumb via auto-bootstrap (status $status)");
        }

        file_put_contents($crumbFile, $crumb);
        return $crumb;
    }

    /**
     * Crumb fetched using a manually-uploaded cookie string (see
     * saveCookieJar). Cached for 1 hour since the cookie itself doesn't
     * refresh automatically — it needs a fresh browser paste when it expires.
     *
     * @throws RuntimeException if the crumb fetch fails
     */
    private function ensureManualCrumb(string $cookieString): string
    {
        $crumbFile = $this->manualCrumbFile();

        if (file_exists($crumbFile) && filemtime($crumbFile) > time() - 3600) {
            return file_get_contents($crumbFile);
        }

        [$crumb, $status] = $this->requestCrumb([CURLOPT_COOKIE => $cookieString]);

        if (!$this->isValidCrumb($crumb, $status)) {
            throw new RuntimeException("Failed to obtain Yahoo Finance session crumb via manual cookie (status $status)");
        }

        file_put_contents($crumbFile, $crumb);
        return $crumb;
    }

    private function fetchViaAuto(string $symbol): string
    {
        $cookieFile = $this->cacheDir . 'session-cookies.txt';
        $crumbFile  = $this->cacheDir . 'session-crumb.txt';

        $crumb = $this->ensureAutoCrumb($cookieFile, $crumbFile);
        [$body, $status, $err] = $this->requestChart($symbol, $crumb, [CURLOPT_COOKIEFILE => $cookieFile]);

        // Session may have expired — bootstrap a fresh one and retry once.
        if ($status === 401 || $status === 403) {
            @unlink($cookieFile);
            @unlink($crumbFile);
            $crumb = $this->ensureAutoCrumb($cookieFile, $crumbFile);
            [$body, $status, $err] = $this->requestChart($symbol, $crumb, [CURLOPT_COOKIEFILE => $cookieFile]);
        }

        if ($body === false || $err !== '') {
            throw new RuntimeException("Yahoo Finance connection failed: $err");
        }
        if ($status >= 400) {
            throw new RuntimeException("Yahoo Finance returned HTTP $status for $symbol (auto session)");
        }

        return $body;
    }

    private function fetchViaManualCookie(string $symbol, string $cookieString): string
    {
        $crumb = $this->ensureManualCrumb($cookieString);
        [$body, $status, $err] = $this->requestChart($symbol, $crumb, [CURLOPT_COOKIE => $cookieString]);

        if ($status === 401) {
            @unlink($this->manualCrumbFile());
            $crumb = $this->ensureManualCrumb($cookieString);
            [$body, $status, $err] = $this->requestChart($symbol, $crumb, [CURLOPT_COOKIE => $cookieString]);
        }

        if ($body === false || $err !== '') {
            throw new RuntimeException("Yahoo Finance connection failed: $err");
        }
        if ($status >= 400) {
            throw new RuntimeException("Yahoo Finance returned HTTP $status for $symbol (manual cookie session)");
        }

        return $body;
    }

    /**
     * Try the automatic session first; if that fails outright (not just a
     * bad HTTP status but a thrown RuntimeException — e.g. fc.yahoo.com
     * itself is unreachable or gated), fall back to a manually-uploaded
     * cookie string if one has been saved via saveCookieJar().
     *
     * @throws RuntimeException if both the automatic session and the manual
     *         fallback (or its absence) fail
     */
    private function fetchChartBody(string $symbol): string
    {
        try {
            return $this->fetchViaAuto($symbol);
        } catch (RuntimeException $autoFailure) {
            $cookieString = $this->loadManualCookieString();
            if ($cookieString === '') {
                throw $autoFailure;
            }
            return $this->fetchViaManualCookie($symbol, $cookieString);
        }
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
            (int) ($meta['regularMarketTime']    ?? 0),
            $lastChecked,
            $tss,
            $ohlc,
        );
    }
}
