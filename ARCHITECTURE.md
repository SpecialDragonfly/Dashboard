# NotQuiteHuman — Rebuild Architecture

**Date:** 2 April 2026  
**Status:** Locked — ready to build

---

## 1. Stack

| Layer | Choice | Notes |
|---|---|---|
| Backend | **Symfony** (latest stable, currently 7.x) | No annotations — YAML routing, explicit service wiring |
| Templating | **Twig** | Replaces PHP string concatenation |
| Database | **MySQL** (existing `notquitehuman` DB) | Plain **PDO** — hand-written domain objects, no ORM, no DBAL, no hydration magic |
| Migrations | **Plain SQL files** | Versioned `.sql` files in `migrations/`, applied via a simple PHP runner script |
| Frontend | **Vanilla HTML/CSS/JS** | No framework, no bundler, no jQuery |
| Fonts | Inter (Google Fonts) | As per prototype |
| Icons | Feather Icons | As per prototype |
| Deployment | **Docker Compose** | PHP-FPM + Nginx containers |

### Why plain PDO?

No Doctrine (ORM or DBAL), no reflection-based hydration, no query builders. Just PDO with prepared statements, and hand-written domain objects with explicit constructors. Repositories do `$stmt->fetch(PDO::FETCH_ASSOC)` and build domain objects manually. SQL stays visible, data flow stays obvious.

### No annotations — how routing works

All routes defined in `config/routes.yaml`. All service wiring in `config/services.yaml`. Controllers are plain PHP classes registered as services — no auto-wiring magic, no `#[Route]` attributes, no `@Route` annotations.

---

## 2. Project Structure

```
notquitehuman.new/
├── config/
│   ├── routes.yaml              # All route definitions
│   ├── services.yaml            # All service wiring (explicit)
│   ├── packages/                # Symfony bundle config
│   │   ├── framework.yaml
│   │   ├── twig.yaml
│   │   └── security.yaml        # Auth config
│   └── routes/
│       └── framework.yaml       # Symfony internal routes
├── public/
│   ├── index.php                # Symfony front controller
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
│   └── Security/
│       └── TokenAuthenticator.php  # Custom authenticator for Bearer tokens
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

## 3. Routing (config/routes.yaml)

```yaml
# -- Public --
app_home:
  path: /
  controller: App\Controller\DashboardController::index
  methods: [GET]

app_login:
  path: /login
  controller: App\Controller\AuthController::loginPage
  methods: [GET]

app_login_submit:
  path: /login
  controller: App\Controller\AuthController::login
  methods: [POST]

app_logout:
  path: /logout
  controller: App\Controller\AuthController::logout
  methods: [POST]

# -- Dashboard (auth required) --
app_dashboard:
  path: /dashboard
  controller: App\Controller\DashboardController::dashboard
  methods: [GET]

# -- Blog (public read, auth write) --
app_blog:
  path: /blog
  controller: App\Controller\BlogController::index
  methods: [GET]

app_blog_post:
  path: /blog/{slug}
  controller: App\Controller\BlogController::show
  methods: [GET]

app_blog_create:
  path: /blog/new
  controller: App\Controller\BlogController::create
  methods: [GET, POST]

app_blog_edit:
  path: /blog/{slug}/edit
  controller: App\Controller\BlogController::edit
  methods: [GET, POST]

app_blog_delete:
  path: /blog/{slug}
  controller: App\Controller\BlogController::delete
  methods: [DELETE]

# -- Ripper (auth required) --
app_ripper:
  path: /ripper
  controller: App\Controller\RipperController::index
  methods: [GET]

app_rip:
  path: /rip
  controller: App\Controller\RipperController::rip
  methods: [POST]

app_ripper_history:
  path: /history
  controller: App\Controller\RipperController::history
  methods: [GET]

app_ripper_download:
  path: /ripper/download/{videoId}
  controller: App\Controller\RipperController::download
  methods: [GET]

# -- Dividends (auth required) --
app_dividends:
  path: /dividends
  controller: App\Controller\DividendController::index
  methods: [GET]

app_portfolio:
  path: /portfolio
  controller: App\Controller\DividendController::getPortfolio
  methods: [GET]

app_add_symbol:
  path: /add-symbol
  controller: App\Controller\DividendController::addSymbol
  methods: [POST]

app_upcoming_dividends:
  path: /upcoming-dividends
  controller: App\Controller\DividendController::getUpcomingDividends
  methods: [GET]

app_update_stock:
  path: /update-stock
  controller: App\Controller\DividendController::updateStock
  methods: [PUT]

app_delete_stock:
  path: /delete-stock
  controller: App\Controller\DividendController::deleteStock
  methods: [DELETE]

app_latest_prices:
  path: /latest-prices
  controller: App\Controller\DividendController::getPrices
  methods: [GET]

app_add_dividend:
  path: /add-dividend
  controller: App\Controller\DividendController::addDividend
  methods: [POST]

# -- Charts (auth required) --
app_charts:
  path: /charts
  controller: App\Controller\ChartController::index
  methods: [GET]

app_charts_data:
  path: /charts/data
  controller: App\Controller\ChartController::getData
  methods: [GET]

app_charts_symbols:
  path: /charts/symbols
  controller: App\Controller\ChartController::getSymbols
  methods: [GET]

app_charts_in_range:
  path: /charts/in-range
  controller: App\Controller\ChartController::inRange
  methods: [GET]
```

---

## 4. Services Wiring (config/services.yaml)

```yaml
services:
  _defaults:
    autowire: false
    autoconfigure: false
    public: false

  # -- PDO Connection (registered as service) --
  pdo.connection:
    class: PDO
    arguments:
      - 'mysql:host=%env(DB_HOST)%;dbname=%env(DB_NAME)%;charset=utf8'
      - '%env(DB_USER)%'
      - '%env(DB_PASS)%'
    calls:
      - [setAttribute, [!php/const PDO::ATTR_ERRMODE, !php/const PDO::ERRMODE_EXCEPTION]]
      - [setAttribute, [!php/const PDO::ATTR_DEFAULT_FETCH_MODE, !php/const PDO::FETCH_ASSOC]]
      - [setAttribute, [!php/const PDO::ATTR_EMULATE_PREPARES, false]]

  # -- Repositories --
  App\Repository\UserRepository:
    arguments:
      $db: '@pdo.connection'

  App\Repository\BlogRepository:
    arguments:
      $db: '@pdo.connection'

  App\Repository\RipperRepository:
    arguments:
      $db: '@pdo.connection'

  App\Repository\PortfolioRepository:
    arguments:
      $db: '@pdo.connection'

  App\Repository\SymbolRepository:
    arguments:
      $db: '@pdo.connection'

  # -- Services --
  App\Service\AuthTokenService:
    arguments:
      $db: '@pdo.connection'

  App\Service\BlogService:
    arguments:
      $blogRepository: '@App\Repository\BlogRepository'

  App\Service\RipperService:
    arguments:
      $ripperRepository: '@App\Repository\RipperRepository'
      $downloadDir: '%kernel.project_dir%/var/downloads/'
      $thumbnailDir: '%kernel.project_dir%/public/assets/ripper/thumbnails/'

  App\Service\DividendService:
    arguments:
      $portfolioRepository: '@App\Repository\PortfolioRepository'

  App\Service\YahooFinanceService:
    arguments:
      $symbolRepository: '@App\Repository\SymbolRepository'
      $cacheDir: '%kernel.project_dir%/var/cache/charts/'

  # -- Security --
  App\Security\TokenAuthenticator:
    arguments:
      $authTokenService: '@App\Service\AuthTokenService'
      $userRepository: '@App\Repository\UserRepository'

  # -- Controllers --
  App\Controller\DashboardController:
    arguments:
      $twig: '@twig'
    tags: ['controller.service_arguments']

  App\Controller\AuthController:
    arguments:
      $twig: '@twig'
      $userRepository: '@App\Repository\UserRepository'
      $authTokenService: '@App\Service\AuthTokenService'
    tags: ['controller.service_arguments']

  App\Controller\BlogController:
    arguments:
      $twig: '@twig'
      $blogService: '@App\Service\BlogService'
    tags: ['controller.service_arguments']

  App\Controller\RipperController:
    arguments:
      $twig: '@twig'
      $ripperService: '@App\Service\RipperService'
    tags: ['controller.service_arguments']

  App\Controller\DividendController:
    arguments:
      $twig: '@twig'
      $dividendService: '@App\Service\DividendService'
      $yahooFinanceService: '@App\Service\YahooFinanceService'
    tags: ['controller.service_arguments']

  App\Controller\ChartController:
    arguments:
      $twig: '@twig'
      $yahooFinanceService: '@App\Service\YahooFinanceService'
    tags: ['controller.service_arguments']
```

---

## 5. Database — New Tables

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

### ✅ In (v1)

| Feature | Source | Notes |
|---|---|---|
| Dashboard | Prototype design | Widget grid, glassmorphism, gradient bg |
| Dashboard widgets | Existing (hardcoded) | Trading knowledge, economic indicators, server debugging, Way of Paul — as Twig partials |
| Dragonfly logo | Existing JS | `dragonfly.js` + `dragonfly-points.js` copied byte-for-byte |
| Christmas countdown | Existing + prototype | Counter in header |
| Days employed counter | Existing + prototype | Counter in header (start: **2024-05-28**) |
| Blog | New | Public by default, hidden posts for auth-only, markdown content, tags |
| Web Comics links | Prototype | Static links with icons |
| YouTube Ripper | Existing (as-is) | Port logic into Symfony controllers, revisit later |
| Portfolio/Dividends | Existing (as-is) | Port logic into Symfony controllers, revisit later |
| Charts | Existing (as-is) | Port logic, keep vendored Chart.js |
| Auth (token-based) | Existing | Carried forward, Symfony security integration |

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

Keeping the existing approach but wrapping it in Symfony's security system:

1. `POST /login` — validates credentials against `users` table, generates 30-day token in `auth_tokens`, returns JSON `{ success, token }`
2. Client stores token in `localStorage` via `AuthManager`
3. All subsequent requests include `Authorization: Bearer <token>`
4. Custom `TokenAuthenticator` validates token on protected routes
5. `POST /logout` — invalidates token server-side, client clears `localStorage`

Symfony's `security.yaml` defines which routes need auth:
- Public: `/`, `/login`, `/blog`, `/blog/{slug}`
- Protected: everything else
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

## 12. Implementation Order

1. **Scaffold** — `composer create-project symfony/skeleton`, add required packages
2. **Config** — routes.yaml, services.yaml, security.yaml, .env with DB creds, PDO service
3. **Docker** — docker-compose.yml (PHP-FPM + Nginx + existing MySQL), Dockerfile
4. **Base template** — Twig layout with header, dragonfly, counters, Feather Icons
5. **Auth** — Login page, TokenAuthenticator, /login + /logout routes
6. **Dashboard** — Widget grid with hardcoded Twig partials (prototype CSS), APOD widget, comics links
7. **Blog** — SQL migration, domain object, repository, service, controller, templates
8. **Ripper** — Port existing logic into Symfony controller/service
9. **Dividends** — Port existing logic
10. **Charts** — Port existing logic
11. **Polish** — Responsive CSS, error pages, cleanup

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
