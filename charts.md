# Charts feature — design discussion

Status: **not started** (per `ARCHITECTURE.md` §0 and §10). This document captures
the design discussion before implementation.

## What already exists to build on

- `YahooFinanceService::getSymbolQuote(string $symbol): StockQuote` — already
  implemented (`src/Service/YahooFinanceService.php`). Fixed to 6-month range,
  weekly candles (`interval=1wk&range=6mo`). Cache: 15 min TTL while UK market
  open, indefinite while closed (`isMarketClosed()`).
- `StockQuote` domain object (`src/Domain/StockQuote.php`) — symbol, price,
  priceTime, lastChecked, timestamps[], ohlc[][open,high,low,close]. Has
  `toArray()` already, ready to `json_encode`.
- `DividendRepository::getPortfolio()` — existing portfolio symbols (what the
  user actually holds), separate from the Freetrade-tradable-universe CSV
  (`var/freetrade-shares.csv`) used by the dividend calendar feature.

## What the old app did (for reference, not a spec)

Found in `notquitehuman.old` / `notquitehuman` (the pre-rebuild codebases):

- `src/Controller/ChartsController.php` + `public/assets/charts/charts.js`
- Stack: jQuery + **ApexCharts** (not Chart.js) + Luxon + Moment — all vendored,
  loaded as separate `<script>` tags, no bundler.
- Shape: a **screener**, not a symbol picker. On page load it fetched a
  hardcoded list of ~700 LSE tickers (`/charts/symbols`), then polled
  `/charts/in-range?symbol=X` for each one, one request per second, via
  `setInterval`. `in-range` computed the prior week's high/low band from a
  1-month/1-week Yahoo quote and returned `true` if `high - low > 100` (pence,
  i.e. >£1 weekly range — a crude volatility/"worth looking at" filter).
  Only symbols that passed got a candlestick chart appended to the page
  (`/charts/data?symbol=X&range=5d&interval=15m`), with weekend gaps shaded
  as annotations.
- This is **not** compatible with the new architecture's stated principles:
  jQuery is explicitly excluded (`CLAUDE.md`: "no jQuery"), and ApexCharts
  contradicts `ARCHITECTURE.md`'s existing mention of Chart.js as the intended
  vendored library. It's useful context for *what problem the page solved*
  (surface volatile/interesting stocks to look at), not for *how* to rebuild it.

## Decisions

- **Scope:** a candlestick chart per stock in the user's portfolio
  (`DividendRepository::getPortfolio()`), plus a candlestick chart per stock
  with a declared dividend. Not a full screener of the ~700-ticker Freetrade
  universe like the old app — that's out of scope.
- **Library: [TradingView Lightweight Charts](https://github.com/tradingview/lightweight-charts)**
  (`lightweight-charts` package, **Apache 2.0**, free — distinct from
  TradingView's paid "Charting Library"/embed widgets, which do carry usage
  restrictions; this one doesn't). Chosen over Chart.js (already proven on
  the dividends page via `chartjs-chart-financial`) because it's purpose-built
  for candlesticks: smaller footprint, native crosshair/tooltip showing OHLC
  on hover, and a native `setMarkers()` API that's a natural fit for the
  planned future feature of tagging news articles to specific dates on the
  chart. Distributed as a single UMD script via CDN — fits the existing
  "CDN `<script>` tag, no bundler" convention already used for Chart.js in
  `templates/dividends/index.html.twig`.
- **Auth:** `/charts*` routes will be auth-protected
  (`->add(TokenAuthMiddleware::class)`), consistent with `/dividends/*` —
  this is personal portfolio data, not public.

- **"Declared dividend" scope:** reuse `DividendService::getUpcomingDividends()`
  as-is (future ex-div date, already cross-referenced against
  `var/freetrade-shares.csv`) — not the raw, unbounded
  `SnowballAnalyticsService::getDeclaredDividends()` list. Keeps the chart
  list small, bounded, and consistent with what the rest of the app already
  considers "relevant."

- **Page layout:** two columns. Left column has two stacked sections —
  portfolio table on top, upcoming-dividend list below. Right column is the
  chart panel only. **Any** row in either left-column section (portfolio or
  dividend) is clickable and opens that stock's candlestick chart in the
  right panel — one uniform interaction, no distinction between the two
  sections. Clicking a different row replaces the current chart. Closing the
  open chart reverts to the default growth chart (never blank). Same
  underlying mechanism as the existing single-chart panel on the dividends
  page (`openChart`/`closeChart`/`drawChart` in `dividends.js`), swapped to
  Lightweight Charts.

- **Portfolio growth chart (default right-panel view):** no buy/sell audit
  log exists anywhere (checked `migrations/004_create_portfolio.sql` — no
  `updated_at`/timestamp column at all; old pre-rebuild codebase has no such
  log either), and `CLAUDE.md` disallows altering existing tables — only
  additive new tables. So history can't be reconstructed; we start tracking
  from today via a **new additive table**, per-symbol (not just a single
  portfolio-total figure), e.g.:
  ```sql
  CREATE TABLE IF NOT EXISTS portfolio_value_history (
      id            INTEGER PRIMARY KEY AUTOINCREMENT,
      date          TEXT    NOT NULL,   -- YYYY-MM-DD
      symbol        TEXT    NOT NULL,
      quantity      REAL    NOT NULL,
      market_price  REAL    NOT NULL,   -- live Yahoo price at snapshot time — NOT portfolio.price (that's cost basis)
      value         REAL    NOT NULL,   -- quantity * market_price
      UNIQUE(date, symbol)
  );
  ```
  New versioned migration file (`migrations/007_...sql`), tracked in
  `migrations_applied` per `CLAUDE.md` convention.
- **Snapshot trigger:** no cron/scheduler exists anywhere in this project
  (`docker-compose.yml` only has `php` + `nginx`) and none will be added.
  Instead, reuse the app's existing "lazy check-and-refresh" idiom (same
  shape as `SnowballAnalyticsService`'s 24h file-cache check and
  `YahooFinanceService`'s 15-min last-checked check): on a request that
  needs it (e.g. loading `/charts`), check whether today's date already has
  rows in `portfolio_value_history`; if not, compute live value per
  portfolio symbol (via `YahooFinanceService::getSymbolQuote()`) and insert
  one row per symbol for today. No new infrastructure.
- **Right-panel default state:** before any upcoming-dividend row is
  clicked, the right panel shows the portfolio growth chart (sum of
  `portfolio_value_history.value` grouped by date, from whenever tracking
  started). Clicking a dividend row swaps the right panel to that symbol's
  candlestick chart. Clicking a different dividend row while one is already
  open **replaces** it. Closing the open dividend chart **reverts to the
  growth chart** (not blank).
- **Data flow / new endpoints:** extend the existing `DividendService`
  directly (new methods for the merged symbol list and for reading/writing
  `portfolio_value_history`) rather than introducing a new `ChartService`
  now. Acknowledged as an interim/pragmatic choice — to be refactored into
  a dedicated service later if `DividendService` gets overloaded. Client-side
  `charts.js` reuses the existing `/dividends/prices?symbols=...` endpoint
  for candlestick OHLC data (accepts a CSV of symbols in one call — no need
  for the old app's one-request-per-symbol polling loop, since the symbol
  list here is small and known upfront).

## Edge cases / gaps found on review pass

**1. ~~Contradiction: "Page layout" bullet is stale.~~ Resolved:** one
left column with two stacked sections (portfolio table, then upcoming-
dividend list), right column is the chart panel. Any row in either section
opens its candlestick chart on the right — one uniform interaction, no
special-casing between portfolio and dividend rows.

**2. ~~`price` means two different things.~~ Resolved:** new column named
`market_price` (not `price`) to make the distinction from `portfolio.price`
(cost basis) unambiguous at the schema level.

**3. ~~Growth chart doesn't account for realised cash.~~ Accepted as a v1
limitation.** `getPortfolio()` filters `deleted = 0`, so a sold stock simply
drops out of the snapshot going forward — the total will show a drop in
value with no offsetting credit for cash received. This chart shows "value
of currently-held positions over time," not true growth including realised
sales. Explicitly out of scope for v1; would need a proper transaction
ledger to fix properly.

**4. ~~Partial failure during a lazy snapshot.~~ Resolved: partial commit.**
`YahooFinanceService::getSymbolQuote()` throws on curl failure or HTTP >= 400.
Snapshot logic commits a row per symbol as each succeeds, independently —
if one symbol fails, the rest of the day's rows are still recorded. Since
storage is per-symbol (not a single aggregate figure), a missing symbol for
a given date is visible/inspectable rather than silently baked into an
opaque total. Failed symbol can be picked up on the next visit (still no
row for that date until it succeeds).

**5. ~~Symbol format mismatch.~~ Resolved: uppercase only, no enforced
suffix.** By design, portfolio symbols aren't LSE-only — a US stock (no
`.L` suffix) is valid and expected. So the fix is narrower than full
normalisation: `addStock` (`src/Controller/DividendController.php`) should
uppercase the symbol input (`strtoupper()`) so case can't cause a mismatch,
but must not force/append a `.L` suffix. Small implementation-time fix in
the existing controller, not a Charts-specific concern, but worth doing
alongside this work since it's now visibly relevant.

**6. ~~Empty states.~~ Resolved:** simple placeholder message for each —
no portfolio stocks yet, no upcoming-dividend matches, and the growth chart
just renders with whatever points exist (even a single one) on day one/two.
Nothing more elaborate needed.

**7. ~~Growth-chart coverage depends on visiting `/charts`.~~ Accepted.**
Direct consequence of the "no cron" decision — any period the page isn't
opened has no snapshot rows, so the line has gaps rather than interpolated
values. Documented tradeoff, not a bug.

**8. ~~Candlestick data can contain nulls/zeros.~~ Resolved: filter, and
treat the resulting gaps as meaningful.** Port `dividends.js`'s existing
`d.o === 0` filter (Yahoo returns `null`/`0` OHLC for illiquid weeks) into
the new `charts.js` so Lightweight Charts (stricter than Chart.js about
well-formed, strictly-ascending series) doesn't throw. Gaps aren't papered
over/interpolated — a gappy chart is itself a signal that the stock is
illiquid/in bad shape, which is useful information rather than a defect.
