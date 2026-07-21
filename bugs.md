# Bugs

## Snowball dividend calendar pagination stops after page 1

**File:** `src/Service/SnowballAnalyticsService.php`
**Method:** `getDeclaredDividends()`
**Line:** ~67 (the `$totalRecords = min(...)` assignment)

### Context

`SnowballAnalyticsService` fetches the LSE dividend calendar from
`https://snowball-analytics.com/extapi/api/public/dividend-calendar/paged`,
50 records (`pageSize`) per page, and is supposed to paginate until all
records are exhausted or a safety cap of `MAX_PAGES = 20` pages is hit. The
results feed `DividendService::getUpcomingDividends()`, which cross-references
them against `var/freetrade-shares.csv` and returns the upcoming ex-dividend
list used by `/dividends/upcoming` (`DividendController::upcoming`).

### The bug

```php
private const MAX_PAGES = 20;    // safety cap
...
$totalRecords = min((int)($data['totalCount'] ?? 1), self::MAX_PAGES);
$recordIndex += (int)$data['pageSize'];
...
} while ($totalRecords > $recordIndex);
```

`MAX_PAGES` is documented as a cap on the number of *pages* fetched, but
this line uses it to cap the *record count* (`$totalRecords`) instead.
`$recordIndex` increases by `pageSize` (50) after every page. Since
`min($data['totalCount'], 20)` can never exceed 20, and 20 < 50, the loop
condition `$totalRecords > $recordIndex` is false after the very first
page — every single time, regardless of how many total records actually
exist. The `do...while` loop therefore always exits after page 1, no
matter what `totalCount` is.

Net effect: any declared dividends that fall after the first 50
paymentDate-sorted records are silently dropped. The result set returned
to `DividendService` is missing data, with no error, warning, or log
entry — it just looks like a shorter-than-expected list.

### How I found it / how to verify

Confirmed live against the real API on 2026-07-05:

```bash
curl -s --max-time 15 "https://snowball-analytics.com/extapi/api/public/dividend-calendar/paged?type=lse&dateType=payment&pageSize=50&page=1&sortBy=paymentDate&sortDirection=asc" \
  -H "Accept: application/json, text/plain, */*" \
  | php -r '
$d = json_decode(file_get_contents("php://stdin"), true);
echo "totalCount: " . ($d["totalCount"] ?? "?") . "\n";
echo "items returned: " . count($d["data"] ?? []) . "\n";
'
```

Result: `totalCount: 98`, `items returned: 50` — i.e. there are 2 pages of
data, but the service's cache (`var/snowball-cache/snowball-declared.json`)
only ever contains page 1's worth of records (17 with `status === "declared"`
out of the 50 on that page). Page 2 (another 48 records) is never
requested.

Ran the real service end-to-end (`DividendService::getUpcomingDividends()`,
via `DividendRepository` + `YahooFinanceService` + `SnowballAnalyticsService`
wired exactly as in `config/container.php`, against the live SQLite
`var/data.db` and live Freetrade CSV) and got only 5 upcoming dividends in
the next two months — plausible-looking, but understated, since page 2 is
never fetched and could contain more near-term `declared` entries.

### Suggested fix

Stop conflating "max records" with "max pages". Something like:

```php
$totalCount = (int) ($data['totalCount'] ?? 0);
$pageSize   = (int) ($data['pageSize'] ?? 50);
$totalPages = (int) ceil($totalCount / max($pageSize, 1));
...
} while ($page <= min($totalPages, self::MAX_PAGES));
```

(Exact shape may differ — the point is: compare `page` against a page
count, not against an accumulated record index compared to a
record-count cap.)

### How to test a fix

1. Delete the stale cache so the fix path actually runs (it doesn't
   overwrite the cache within its 24h TTL otherwise):
   ```bash
   rm -f /home/dom/Projects/Old/Projects/notquitehuman.new/var/snowball-cache/snowball-declared.json
   ```
2. Run the live-API sanity check above to see current `totalCount` (it
   changes daily) and confirm it's still > 50 so the pagination bug would
   actually be exercised. If `totalCount <= 50` on test day, this won't
   reproduce/verify — check back later or temporarily lower `pageSize` in
   the service to force multiple pages.
3. Run the service directly (bypasses Twig/Slim, only needs `ext-curl` +
   `pdo_sqlite`, `composer install` for autoloading):
   ```php
   <?php
   require '/home/dom/Projects/Old/Projects/notquitehuman.new/vendor/autoload.php';

   use App\Repository\DividendRepository;
   use App\Service\DividendService;
   use App\Service\SnowballAnalyticsService;
   use App\Service\YahooFinanceService;

   $root = '/home/dom/Projects/Old/Projects/notquitehuman.new';
   $pdo = new PDO('sqlite:' . $root . '/var/data.db');
   $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
   $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

   $repo     = new DividendRepository($pdo);
   $yahoo    = new YahooFinanceService($root . '/var/yahoo-cache/', $repo);
   $snowball = new SnowballAnalyticsService($root . '/var/snowball-cache/');
   $service  = new DividendService($repo, $yahoo, $snowball, $root . '/var/freetrade-shares.csv');

   $upcoming = $service->getUpcomingDividends();
   echo count($upcoming) . " entries\n";
   foreach ($upcoming as $d) {
       printf("%-8s %-35s %-12s %s %s\n", $d['symbol'], $d['name'], $d['exDivDate'], $d['amount'], $d['currency']);
   }
   ```
4. Check `var/snowball-cache/snowball-declared.json` after running —
   `count()` on the decoded JSON should now exceed 50 whenever
   `totalCount > 50` (before the fix it was capped at whatever page 1
   contained). Cross-check a couple of tickers you know are declared but
   sort late in payment-date order (page 2+) actually show up.
5. No automated test suite exists in this repo (`CLAUDE.md` confirms) —
   this is a manual verification only, using the live third-party API.
   Rate limit yourself; don't hammer `snowball-analytics.com` in a loop.
