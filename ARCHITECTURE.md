# NotQuiteHuman — Rebuild Architecture

**Date:** 2 April 2026 (original plan) — **implementation status updated 5 July 2026**
**Status:** Locked (stack/principles) — actively building; sections 3, 4, 6, 12 below reflect actual code, not the original plan

---

## 0. Current Implementation Status (as of 2026-07-05)

Everything below in sections 1–2 (stack, principles, structure) still holds. Sections 3 ("Routing"), 4 ("Services Wiring"), 6 ("Features"), and 12 ("Implementation Order") originally described the *plan* — they've been rewritten in place to match what's actually in the repo, since the real implementation has diverged in several ways:

- **Dividends routing is RESTful, not action-named.** `config/routes.php` uses `/dividends/portfolio/{symbol}` (GET/POST/PUT/DELETE) instead of the originally planned `/add-symbol`, `/update-stock`, `/delete-stock`, `/latest-prices` style. `DividendController` methods are `portfolio`, `addStock`, `updateStock`, `deleteStock`, `prices`, `upcoming`, `addPayment`.
- **Charts feature not started.** No `ChartController`, no `/charts*` routes, no `Domain/PortfolioItem.php`/`DividendPayment.php`. `StockQuote` (OHLC) already exists on `YahooFinanceService::getSymbolQuote()` in anticipation of it.
- **Dividend calendar source changed.** Original plan scraped `dividenddata.co.uk`. Actual implementation is `SnowballAnalyticsService` (implements `DividendCalendarInterface`) hitting `snowball-analytics.com`'s public dividend-calendar API, paginated, 24h cache in `var/snowball-cache/`. Declared dividends are cross-referenced against a Freetrade-tradable-shares CSV (`var/freetrade-shares.csv`) in `DividendService::getUpcomingDividends()`.
- **`OptionalAuthMiddleware` added** (not in original plan) — same token-lookup logic as `TokenAuthMiddleware` but never blocks the request; it just attaches `user` to the request attribute if a valid token is present. Used on `/` so the dashboard/homepage can render differently for anon vs logged-in without forcing a redirect.
- **Auth is now hybrid cookie + Bearer, not pure localStorage.** `AuthController::login` sets both a JSON `token` in the response body *and* an `HttpOnly` cookie (`nqh_token`). `auth.js`'s `AuthManager` still stores the token in `localStorage` and sends it as `Authorization: Bearer`. Both `TokenAuthMiddleware` and `OptionalAuthMiddleware` check the `Bearer` header first, falling back to the `nqh_token` cookie.
- **Repository/table naming differs slightly from the original plan:** it's `DividendRepository` (not `PortfolioRepository` + `SymbolRepository` as separate classes) — one repository backs both the `portfolio` and `symbols` tables, since `symbols` only tracks per-ticker Yahoo cache freshness (`last-checked`).
- **Domain objects that exist today:** `BlogPost`, `RippedFile`, `Stock` (portfolio row), `StockQuote` (Yahoo OHLC), `User`. No `PortfolioItem` or `DividendPayment` domain classes yet — dividend payments are summed in SQL (`DividendRepository::getPortfolio()` does the `LEFT JOIN ... COALESCE(SUM(...))`), not hydrated as objects.
- **A leftover/ahead-of-need test exists:** `tests/Service/ExDividendDateRegexTest.php` tests a regex for scraping ex-dividend dates out of Yahoo Finance HTML (`<span>Ex-Dividend Date</span><span>...</span>`). Nothing in `src/` currently uses this regex — it looks like a prepared-but-unwired scraper fallback, possibly for when the Snowball API is unavailable for a ticker. Worth checking before assuming it's dead code.
- **Blog has an image upload endpoint** not in the original plan: `POST /blog/upload` (auth required, must be routed before `/blog/new`/`/blog/{slug}` in `config/routes.php` to avoid slug collision) — saves to `public/assets/uploads/`, validates MIME + extension, returns `{ url }` for use in the markdown editor.
- **Ripper supports streaming, not just download.** `GET /ripper/stream/{videoId}` added alongside `/ripper/download/{videoId}` — serves `audio/mpeg` inline instead of forcing an attachment download. `RipperService` shells out to `yt-dlp`/`ffmpeg` via `exec()` with `escapeshellarg()`, and reconciles filesystem state (mp3/webp/json files dropping into `var/ripper/`) via `cleanup()`, called on every `getHistory()`.
- **Dashboard widgets built so far:** `admin_links`, `blog`, `comics`, `economic`, `server_debugging`, `trading`, `way_of_paul` — all present in `templates/dashboard/_widgets/`.
- **Migrations 001–006 applied** (SQLite, via `migrations/run.php`): `users`+`auth_tokens`, `blog_posts`, `ripped_files`, `portfolio`, `dividend_payments`, `symbols`.

**Not yet built:** Charts feature entirely (controller, routes, templates, `charts.css`/`charts.js`). MySQL cutover (still SQLite `var/data.db`). No CSS/JS files exist yet for ripper/dividends beyond what's needed to render current templates — verify before assuming `ripper.css`/`dividends.css` exist.

---

## 1. Stack

| Layer | Choice | Notes |
|---|---|---|
| Backend | **Slim 4** | Micro-framework. PSR-7/PSR-15. PHP config only — no YAML, no annotations |
| DI Container | **PHP-DI 7** | Explicit PHP definitions — no autowiring |
| Templating | **Twig** | Replaces PHP string concatenation |
| Database | **MySQL** (existing `notquitehuman` DB) | Plain **PDO** — hand-written domain objects, no ORM, no DBAL, no hydration magic |
| Migrations | **Plain SQL files** | Versioned `.sql` files in `migrations/`, applied via a simple PHP runner script |
| Frontend | **Vanilla HTML/CSS/JS** | No framework, no bundler, no jQuery |
| Fonts | Inter (Google Fonts) | As per prototype |
| Icons | Feather Icons | As per prototype |
| Deployment | **Docker Compose** | PHP-FPM + Nginx containers |

### Why plain PDO?

No Doctrine (ORM or DBAL), no reflection-based hydration, no query builders. Just PDO with prepared statements, and hand-written domain objects with explicit constructors. Repositories do `$stmt->fetch(PDO::FETCH_ASSOC)` and build domain objects manually. SQL stays visible, data flow stays obvious.

### No annotations — how routing and DI work

Routes are defined in `config/routes.php` as a closure over `Slim\App`. Service wiring is in `config/container.php` as a plain PHP array of closures — no autowiring, no reflection, no magic. Middleware is registered in `config/middleware.php`.

---

## 2. Project Structure

```
notquitehuman.new/
├── config/
│   ├── container.php            # PHP-DI service definitions (explicit, no autowiring)
│   ├── routes.php               # Slim route registrations + middleware attachment
│   └── middleware.php           # App-wide middleware stack
├── public/
│   ├── index.php                # Slim bootstrap
│   └── assets/
│       ├── css/
│       │   ├── common.css       # Base styles + layout
│       │   ├── dashboard.css    # Dashboard-specific
│       │   ├── blog.css         # Blog styles
│       │   ├── ripper.css       # Ripper page
│       │   ├── dividends.css    # Dividends page
│       │   └── charts.css       # Charts page
│       ├── js/
│       │   ├── common.js        # Shared utilities (no jQuery)
│       │   ├── auth.js          # AuthManager (token handling)
│       │   ├── dragonfly.js     # Logo drawing — COPIED VERBATIM
│       │   ├── dragonfly-points.js  # Point data — COPIED VERBATIM
│       │   ├── dashboard.js     # Counters, APOD fetch
│       │   ├── blog.js          # Blog CRUD
│       │   ├── ripper.js        # Ripper interactions
│       │   ├── dividends.js     # Portfolio UI
│       │   └── charts.js        # Chart rendering
│       └── vendor/              # Vendored JS libs (Chart.js, Luxon, etc.)
├── src/
│   ├── Controller/
│   │   ├── DashboardController.php
│   │   ├── BlogController.php
│   │   ├── AuthController.php
│   │   ├── RipperController.php
│   │   ├── DividendController.php
│   │   └── ChartController.php
│   ├── Service/
│   │   ├── BlogService.php
│   │   ├── RipperService.php
│   │   ├── DividendService.php
│   │   ├── YahooFinanceService.php
│   │   └── AuthTokenService.php
│   ├── Repository/
│   │   ├── BlogRepository.php
│   │   ├── RipperRepository.php
│   │   ├── PortfolioRepository.php
│   │   ├── SymbolRepository.php
│   │   └── UserRepository.php
│   ├── Domain/
│   │   ├── BlogPost.php         # Plain PHP object, explicit constructor
│   │   ├── User.php
│   │   ├── RippedFile.php
│   │   ├── PortfolioItem.php
│   │   ├── StockQuote.php
│   │   └── DividendPayment.php
│   └── Middleware/
│       └── TokenAuthMiddleware.php  # PSR-15 middleware — validates Bearer token
├── templates/
│   ├── base.html.twig           # Master layout (head, header, footer)
│   ├── dashboard/
│   │   ├── index.html.twig
│   │   └── _widgets/            # Hardcoded widget partials
│   │       ├── trading.html.twig
│   │       ├── economic.html.twig
│   │       ├── server_debugging.html.twig
│   │       ├── way_of_paul.html.twig
│   │       ├── comics.html.twig
│   │       └── admin_links.html.twig
│   ├── blog/
│   │   ├── index.html.twig     # Blog listing
│   │   ├── post.html.twig      # Single post view
│   │   └── form.html.twig      # Create/edit form
│   ├── auth/
│   │   └── login.html.twig
│   ├── ripper/
│   │   └── index.html.twig
│   ├── dividends/
│   │   └── index.html.twig
│   └── charts/
│       └── index.html.twig
├── migrations/
│   ├── run.php                  # Simple migration runner (tracks applied in DB)
│   └── 001_create_blog_posts.sql
├── .env                         # DB creds, app secret, env vars
├── composer.json
├── docker-compose.yml
├── Dockerfile
└── README.md
```

---

## 3. Routing (config/routes.php) — actual, as implemented

Auth-protected routes have `->add(TokenAuthMiddleware::class)` chained directly. `/` uses `OptionalAuthMiddleware` (attaches user if present, never blocks). Public routes have no middleware. Charts routes from the original plan don't exist yet.

```php
return function (App $app) {
    // -- Housekeeping --
    $app->get('/favicon.ico', function ($req, $res) { return $res->withStatus(204); });

    // -- Public --
    $app->get('/', [DashboardController::class, 'index'])->add(OptionalAuthMiddleware::class);
    $app->get('/login',  [AuthController::class, 'loginPage']);
    $app->post('/login', [AuthController::class, 'login']);
    $app->post('/logout',[AuthController::class, 'logout']);

    // -- Blog (public read) --
    $app->get('/blog',        [BlogController::class, 'index']);

    // -- Blog write (auth required) — /blog/new must come before /blog/{slug} --
    $app->post('/blog/upload',      [BlogController::class, 'upload'])->add(TokenAuthMiddleware::class);
    $app->get('/blog/new',          [BlogController::class, 'create'])->add(TokenAuthMiddleware::class);
    $app->post('/blog/new',         [BlogController::class, 'create'])->add(TokenAuthMiddleware::class);
    $app->get('/blog/{slug}/edit',  [BlogController::class, 'edit'])->add(TokenAuthMiddleware::class);
    $app->post('/blog/{slug}/edit', [BlogController::class, 'edit'])->add(TokenAuthMiddleware::class);
    $app->delete('/blog/{slug}',    [BlogController::class, 'delete'])->add(TokenAuthMiddleware::class);
    $app->get('/blog/{slug}',       [BlogController::class, 'show']);

    // -- Dividends (auth required) --
    $app->get('/dividends',                       [DividendController::class, 'index'])->add(TokenAuthMiddleware::class);
    $app->get('/dividends/portfolio',              [DividendController::class, 'portfolio'])->add(TokenAuthMiddleware::class);
    $app->post('/dividends/portfolio',             [DividendController::class, 'addStock'])->add(TokenAuthMiddleware::class);
    $app->put('/dividends/portfolio/{symbol}',     [DividendController::class, 'updateStock'])->add(TokenAuthMiddleware::class);
    $app->delete('/dividends/portfolio/{symbol}',  [DividendController::class, 'deleteStock'])->add(TokenAuthMiddleware::class);
    $app->get('/dividends/prices',                 [DividendController::class, 'prices'])->add(TokenAuthMiddleware::class);
    $app->get('/dividends/upcoming',               [DividendController::class, 'upcoming'])->add(TokenAuthMiddleware::class);
    $app->post('/dividends/payment',               [DividendController::class, 'addPayment'])->add(TokenAuthMiddleware::class);

    // -- Ripper (auth required) --
    $app->get('/ripper',                    [RipperController::class, 'index'])->add(TokenAuthMiddleware::class);
    $app->post('/rip',                      [RipperController::class, 'rip'])->add(TokenAuthMiddleware::class);
    $app->get('/history',                   [RipperController::class, 'history'])->add(TokenAuthMiddleware::class);
    $app->get('/ripper/download/{videoId}', [RipperController::class, 'download'])->add(TokenAuthMiddleware::class);
    $app->get('/ripper/stream/{videoId}',   [RipperController::class, 'stream'])->add(TokenAuthMiddleware::class);

    // -- Charts — NOT YET IMPLEMENTED --
};
```

---

## 4. Services Wiring (config/container.php) — actual, as implemented

Plain PHP-DI definitions. Every dependency is explicit — no autowiring.

```php
return [
    // -- Twig --
    Environment::class => function () {
        $loader = new FilesystemLoader(dirname(__DIR__) . '/templates');
        return new Environment($loader, [
            'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
            'cache' => ($_ENV['APP_ENV'] ?? 'dev') === 'prod'
                ? dirname(__DIR__) . '/var/cache/twig'
                : false,
        ]);
    },

    // -- Database --
    // Currently SQLite for local development. Will switch to MySQL before production.
    // When switching: replace DSN and add DB_HOST/DB_NAME/DB_USER/DB_PASS to .env.
    PDO::class => function () {
        $pdo = new PDO('sqlite:' . dirname(__DIR__) . '/var/data.db');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        return $pdo;
    },

    // -- Repositories --
    // One DividendRepository backs both `portfolio` and `symbols` tables —
    // there's no separate PortfolioRepository/SymbolRepository.
    UserRepository::class     => fn(ContainerInterface $c) => new UserRepository($c->get(PDO::class)),
    BlogRepository::class     => fn(ContainerInterface $c) => new BlogRepository($c->get(PDO::class)),
    RipperRepository::class   => fn(ContainerInterface $c) => new RipperRepository($c->get(PDO::class)),
    DividendRepository::class => fn(ContainerInterface $c) => new DividendRepository($c->get(PDO::class)),

    // -- Services --
    AuthTokenService::class => fn(ContainerInterface $c) => new AuthTokenService($c->get(PDO::class)),
    BlogService::class      => fn(ContainerInterface $c) => new BlogService($c->get(BlogRepository::class)),
    YahooFinanceService::class => fn(ContainerInterface $c) => new YahooFinanceService(
        dirname(__DIR__) . '/var/yahoo-cache/',
        $c->get(DividendRepository::class),
    ),
    // Declared-dividend calendar source — bound to Snowball Analytics today.
    // Swap the DividendCalendarInterface binding to change source without touching DividendService.
    SnowballAnalyticsService::class => fn(ContainerInterface $c) => new SnowballAnalyticsService(
        dirname(__DIR__) . '/var/snowball-cache/',
    ),
    DividendCalendarInterface::class => fn(ContainerInterface $c) => $c->get(SnowballAnalyticsService::class),
    DividendService::class  => fn(ContainerInterface $c) => new DividendService(
        $c->get(DividendRepository::class),
        $c->get(YahooFinanceService::class),
        $c->get(DividendCalendarInterface::class),
        dirname(__DIR__) . '/var/freetrade-shares.csv',
    ),
    RipperService::class    => fn(ContainerInterface $c) => new RipperService(
        $c->get(RipperRepository::class),
        dirname(__DIR__) . '/var/ripper/',
        dirname(__DIR__) . '/public/assets/ripper/thumbnails/',
    ),

    // -- Middleware --
    TokenAuthMiddleware::class => fn(ContainerInterface $c) => new TokenAuthMiddleware(
        $c->get(AuthTokenService::class),
        $c->get(UserRepository::class),
    ),
    // Attaches `user` to the request if a valid token is present, but never blocks —
    // used on public routes (e.g. `/`) that render differently when logged in.
    OptionalAuthMiddleware::class => fn(ContainerInterface $c) => new OptionalAuthMiddleware(
        $c->get(AuthTokenService::class),
        $c->get(UserRepository::class),
    ),

    // -- Controllers --
    DashboardController::class => fn(ContainerInterface $c) => new DashboardController(
        $c->get(Environment::class),
        $c->get(BlogService::class),
    ),
    AuthController::class => fn(ContainerInterface $c) => new AuthController(
        $c->get(Environment::class),
        $c->get(UserRepository::class),
        $c->get(AuthTokenService::class),
    ),
    BlogController::class => fn(ContainerInterface $c) => new BlogController(
        $c->get(Environment::class),
        $c->get(BlogService::class),
    ),
    RipperController::class => fn(ContainerInterface $c) => new RipperController(
        $c->get(Environment::class),
        $c->get(RipperService::class),
    ),
    DividendController::class => fn(ContainerInterface $c) => new DividendController(
        $c->get(Environment::class),
        $c->get(DividendService::class),
    ),

    // -- ChartController — NOT YET IMPLEMENTED --
];
```

---

## 5. Database — New Tables

**Current target: SQLite** (`var/data.db`), for simplicity during development. This will switch to MySQL (`notquitehuman` DB) before production. The schema and migration runner are the same either way; only the PDO DSN in `config/container.php` changes.

The existing tables (`users`, `auth_tokens`, `ripped_files`, `portfolio`, `dividend_payments`, `symbols`, `phinxlog`) are **untouched**. New SQL migration adds:

### `blog_posts`

| Column | Type | Notes |
|---|---|---|
| id | int (PK, auto) | |
| user_id | int | FK → users.id |
| title | varchar(255) | |
| slug | varchar(255) | unique, URL-safe |
| content | text | Markdown source |
| tags | varchar(255) nullable | Comma-separated |
| published | boolean | default true — false = hidden post, not visible to public |
| created_at | datetime | |
| updated_at | datetime | |

### `migrations_applied`

| Column | Type | Notes |
|---|---|---|
| id | int (PK, auto) | |
| filename | varchar(255) | unique |
| applied_at | datetime | |

Simple tracking table for the SQL migration runner. No overlap with the existing `phinxlog`.

That's it for now. No blog comments, no categories table — keep it lean. Tags are a simple comma string; we can normalise later if needed.

---

## 6. Features — What's In, What's Out

### ✅ In (v1) — build status as of 2026-07-05

| Feature | Source | Status |
|---|---|---|
| Dashboard | Prototype design | **Built** — widget grid, `OptionalAuthMiddleware` for anon/logged-in rendering |
| Dashboard widgets | Existing (hardcoded) | **Built** — `admin_links`, `blog`, `comics`, `economic`, `server_debugging`, `trading`, `way_of_paul` partials |
| Dragonfly logo | Existing JS | **Built** — copied byte-for-byte |
| Christmas countdown | Existing + prototype | **Built** — `AbstractController::baseVars()` |
| Days employed counter | Existing + prototype | **Built** — start: **2024-05-28**, `AbstractController::baseVars()` |
| Blog | New | **Built** — CRUD, markdown via `league/commonmark`, tags, published toggle, image upload (`/blog/upload`) |
| Web Comics links | Prototype | **Built** — as `comics.html.twig` widget |
| YouTube Ripper | Existing (as-is) | **Built** — rip/history/download + streaming (`/ripper/stream/{videoId}`) |
| Portfolio/Dividends | Existing (as-is) | **Built** — RESTful routes, calendar source swapped to Snowball Analytics (see §0) |
| Charts | Existing (as-is) | **Not started** — no controller, routes, or templates yet |
| Auth (token-based) | Existing | **Built** — hybrid Bearer header + `HttpOnly` cookie (see §0) |

### ❌ Out

| Feature | Reason |
|---|---|
| Canvas drawing | Experiment, unused |
| Canvas WebSocket | Experiment, unused |
| Chat example | No backend, experiment |
| Base64 tool | Trivial utility |
| Recipe system | Never built |
| `seniordev.html` | Standalone doc, not part of app |
| Blog roll (friends' blogs) | Most are dead. Can add back later |

---

## 7. Template Structure

### Base Layout (`templates/base.html.twig`)

```
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{% block title %}NotQuiteHuman{% endblock %}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/common.css">
    {% block stylesheets %}{% endblock %}
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="/assets/js/dragonfly-points.js"></script>
    <script src="/assets/js/dragonfly.js"></script>
    <script src="/assets/js/auth.js"></script>
    <script src="/assets/js/common.js"></script>
    {% block javascripts %}{% endblock %}
</head>
<body>
    <header class="header">
        <div class="header-left">
            <a href="/dashboard" class="logo-container" title="Home">
                <canvas id="dragonfly" width="80" height="120"
                        class="dragonfly-canvas"></canvas>
                <span class="logo-text">NotQuiteHuman</span>
            </a>
        </div>
        <div class="header-right">
            {% block header_counters %}
            <div class="counter-card">...</div> {# Christmas #}
            <div class="counter-card">...</div> {# Days employed #}
            {% endblock %}
        </div>
    </header>

    <main>
        {% block body %}{% endblock %}
    </main>

    <script>
        feather.replace();
        draw(-10, 0, 1.2, 0.75);
    </script>
</body>
</html>
```

Key detail: The dragonfly `draw()` call uses the exact same parameters as the existing site: `draw(-10, 0, 1.2, 0.75)`. The canvas is 80×120 with `transform: rotate(90deg)`. This must not change.

### Dashboard widgets

The static content widgets (trading knowledge, economic indicators, server debugging, Way of Paul) stay as hardcoded Twig partials in `templates/dashboard/_widgets/`. They're reference material, not blog content. The dashboard template includes them with `{% include 'dashboard/_widgets/trading.html.twig' %}` etc.

---

## 8. Authentication Flow

1. `POST /login` — validates credentials against `users` table, generates 30-day token in `auth_tokens`, returns JSON `{ success, token }`
2. Client stores token in `localStorage` via `AuthManager`
3. All subsequent requests include `Authorization: Bearer <token>`
4. `TokenAuthMiddleware` (PSR-15, `src/Middleware/TokenAuthMiddleware.php`) validates the token on protected routes
5. `POST /logout` — invalidates token server-side, client clears `localStorage`

Route protection is declared per-route in `config/routes.php` via `->add(TokenAuthMiddleware::class)`:
- Public (no middleware): `/`, `/login`, `/blog`, `/blog/{slug}`
- Protected (middleware attached): everything else
- Blog listing/view: public users see only `published = true` posts; logged-in users see all

---

## 9. Blog Feature Detail

### Behaviour
- Blog listing at `/blog` — **public**, shows all published posts, newest first
- Individual post at `/blog/{slug}` — **public**, full markdown-rendered content
- Hidden posts (`published = false`) are only visible when logged in
- Create at `/blog/new` (auth required) — title, content (markdown textarea), tags, published toggle
- Edit at `/blog/{slug}/edit` (auth required)
- Delete via `DELETE /blog/{slug}` (auth required)

### Markdown Rendering
Server-side via `league/commonmark` (Composer package). Content stored as raw markdown, rendered to HTML in the Twig template. No WYSIWYG editor — just a textarea. The prototype had toolbar buttons for bold/italic/link; we can keep those as JS helpers that insert markdown syntax.

### Slugs
Auto-generated from title on creation. If a slug collision occurs, append `-2`, `-3`, etc.

---

## 10. Domain Objects — Example Pattern

No magic. No reflection. Just plain PHP classes with explicit constructors.

```php
// src/Domain/BlogPost.php
class BlogPost
{
    private int $id;
    private int $userId;
    private string $title;
    private string $slug;
    private string $content;
    private ?string $tags;
    private bool $published;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        int $id,
        int $userId,
        string $title,
        string $slug,
        string $content,
        ?string $tags,
        bool $published,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->title = $title;
        $this->slug = $slug;
        $this->content = $content;
        $this->tags = $tags;
        $this->published = $published;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    // Getters...
}
```

```php
// src/Repository/BlogRepository.php
class BlogRepository
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function findPublished(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM blog_posts WHERE published = 1 ORDER BY created_at DESC'
        );
        $stmt->execute();
        $posts = [];
        while ($row = $stmt->fetch()) {
            $posts[] = $this->hydrate($row);
        }
        return $posts;
    }

    private function hydrate(array $row): BlogPost
    {
        return new BlogPost(
            (int) $row['id'],
            (int) $row['user_id'],
            $row['title'],
            $row['slug'],
            $row['content'],
            $row['tags'],
            (bool) $row['published'],
            new \DateTimeImmutable($row['created_at']),
            new \DateTimeImmutable($row['updated_at'])
        );
    }
}
```

Every repository has its own `hydrate()` that explicitly maps columns → constructor args. No generics, no reflection, no surprises.

---

## 11. Dragonfly Logo — Migration Notes

These files are sacred. Copy them exactly:

- `public/assets/js/dragonfly-points.js` — massive array of `{x, y}` deltas, assigned to `window.dragonflypoints`
- `public/assets/js/dragonfly.js` — the `draw()` function + `window.onload` handler

**Critical:** The `draw()` function renders by iterating over the point deltas and drawing 1px arcs on a canvas. It depends on:
- A `<canvas id="dragonfly" width="80" height="120">` element being present
- Being called with exact params: `draw(-10, 0, 1.2, 0.75)`
- The canvas being rotated 90° via CSS (`transform: rotate(90deg)`)
- The fill colour is `'white'` — the canvas needs a dark background

**Decision:** Keep the fill white. The canvas element gets a dark background colour so the white dots are visible. The prototype already has `border: 2px solid rgba(102, 126, 234, 0.3)` on it — we'll add a dark `background` (e.g. `#333` or the gradient's dark end) to the canvas CSS.

---

## 12. Implementation Order — progress as of 2026-07-05

1. ✅ **Scaffold** — `composer init`, add Slim 4 + PHP-DI + Twig + phpdotenv
2. ✅ **Config** — `config/routes.php`, `config/container.php`, `config/middleware.php`, `.env`, PDO (SQLite)
3. 🟡 **Docker** — `docker-compose.yml` + `Dockerfile` exist; still pointed at SQLite, not the existing MySQL DB
4. ✅ **Base template** — Twig layout with header, dragonfly, counters, Feather Icons
5. ✅ **Auth** — Login page, `AuthTokenService`, `/login` + `/logout` (now hybrid cookie + Bearer, see §0)
6. ✅ **Dashboard** — Widget grid with hardcoded Twig partials, comics links (APOD widget not confirmed — check `dashboard/index.html.twig` before assuming)
7. ✅ **Blog** — SQL migration, domain object, repository, service, controller, templates, image upload
8. ✅ **Ripper** — Ported into Slim controller/service, plus streaming endpoint
9. ✅ **Dividends** — Ported, RESTful routes, calendar source swapped to Snowball Analytics
10. ❌ **Charts** — Not started
11. ❌ **Polish** — Responsive CSS, error pages, cleanup — not assessed; no test suite/linter configured per `CLAUDE.md`

**Next logical steps:** (a) Charts feature (controller + routes + `ChartController` wiring + templates/JS using vendored Chart.js), (b) MySQL cutover for Docker/production, (c) decide whether `ExDividendDateRegexTest`'s scraper regex needs a home in `src/` or should be deleted as dead code.

---

## 13. Resolved Decisions

| Question | Answer |
|---|---|
| Dragonfly fill colour | Keep white — canvas gets dark background |
| Blog visibility | Public by default, with hidden posts (`published=false`) for auth-only |
| Days employed counter | **May 28, 2024** |
| Deployment | **Docker Compose** |
| Static dashboard content | **Keep as hardcoded Twig widget partials** |
| Database layer | **Plain PDO** — no Doctrine, no DBAL, no reflection hydration |

---

*Architecture locked. Ready to build. 🪶*
