# NotQuiteHuman — Site Audit & Architecture Findings

**Date:** 2 April 2026  
**Project:** `~/Projects/notquitehuman` → complete rebuild as `~/Projects/notquitehuman.new`  
**Constraint:** The MySQL database (`notquitehuman`) must be preserved intact.

---

## 1. Current Stack

| Layer | Technology |
|---|---|
| Language | PHP (no framework, vanilla) |
| Routing | `phroute/phroute` |
| Database | MySQL via PDO (host: `host.docker.internal`) |
| Migrations | Phinx (`robmorgan/phinx`) |
| Environment | `vlucas/phpdotenv` |
| Email | SendGrid (`sendgrid/sendgrid`) |
| HTTP client | Guzzle (`guzzlehttp/guzzle`) |
| Deployment | Docker (PHP image, needs custom Dockerfile for PDO + yt-dlp) |
| Frontend | Vanilla JS + jQuery, no build step, no bundler |

---

## 2. Application Features (Routes & Functionality)

### 2.1 Public
| Route | Description |
|---|---|
| `GET /` | Returns "hello world" (placeholder) |
| `GET /login` | Login page (HTML form) |
| `POST /login` | Authenticates user, returns JSON with Bearer token (30-day) |
| `POST /logout` | Invalidates session + token |

### 2.2 Authenticated — Dashboard
| Route | Description |
|---|---|
| `GET /dashboard` | Main dashboard — widget-based layout with: NASA APOD, "Way of Paul" tenets, blog roll, trading knowledge, economic indicators, server debugging checklist, admin links |

### 2.3 Authenticated — YouTube Ripper
| Route | Description |
|---|---|
| `GET /ripper` | Ripper UI |
| `POST /rip` | Submits a YouTube URL for audio extraction (yt-dlp → mp3) |
| `GET /history` | Returns JSON list of ripped files (runs cleanup first) |
| `GET /ripper/download/{videoId}` | Downloads the ripped mp3 |

### 2.4 Authenticated — Dividend Tracker / Portfolio
| Route | Description |
|---|---|
| `GET /dividends` | Dividend tracker UI |
| `GET /portfolio` | JSON: portfolio holdings with aggregated dividend payments |
| `POST /add-symbol` | Add stock to portfolio (or increase quantity if exists) |
| `PUT /update-stock` | Update ex-div date and dividend amount |
| `DELETE /delete-stock` | Soft-delete a stock |
| `GET /upcoming-dividends` | Scrapes dividenddata.co.uk, filters by FreeTrade share list |
| `GET /latest-prices` | Fetches live prices from Yahoo Finance (15-min cache) |
| `POST /add-dividend` | Record a dividend payment |

### 2.5 Authenticated — Charts
| Route | Description |
|---|---|
| `GET /charts` | Chart UI (Chart.js v4 + financial plugin + Luxon) |
| `GET /charts/data` | OHLC data from Yahoo Finance |
| `GET /charts/symbols` | List of tracked symbols from DB |
| `GET /charts/in-range` | Weekly high/low range for a symbol |

### 2.6 Authenticated — Utilities
| Route | Description |
|---|---|
| `GET /base64` | Base64 encode/decode tool |
| `GET /canvas` | Canvas drawing tool |
| `GET /canvas-ws` | Canvas with WebSocket support |
| `GET /chat-example` | Chat example (STOMP-based) |

### 2.7 Unlinked / Stubs
- **Recipe system** (`src/Recipe/RecipeService.php`) — empty stub with method signatures, no DB tables, no routes. Looks like it was planned but never built.
- **`seniordev.html`** — standalone HTML page about senior developer evaluation criteria. Not routed.
- **`youtube-dl.txt`** — CLI reference notes for yt-dlp. Not served.

---

## 3. Database Schema

Database: `notquitehuman` / MySQL / Phinx-managed migrations (7 total)

### Tables

#### `users`
| Column | Type | Notes |
|---|---|---|
| id | int (PK, auto) | |
| username | string | |
| password | string | bcrypt hash |
| created | datetime | |

#### `auth_tokens`
| Column | Type | Notes |
|---|---|---|
| id | int (PK, auto) | |
| user_id | int (FK → users.id, CASCADE) | |
| token | varchar(64) | unique, random hex |
| expires_at | datetime | indexed |
| created_at | datetime | default CURRENT_TIMESTAMP |

#### `ripped_files`
| Column | Type | Notes |
|---|---|---|
| id | int (PK, auto) | |
| video_id | string | YouTube video ID, indexed |
| path | string | local filesystem path to mp3 |
| url | string | original YouTube URL, indexed |
| title | string | populated from yt-dlp info JSON |
| thumbnail | string | path to .webp thumbnail |
| created | datetime | |

#### `portfolio`
| Column | Type | Notes |
|---|---|---|
| id | int (PK, auto) | |
| symbol | string | ticker symbol |
| name | string | company name |
| quantity | float | shares held |
| price | float | average purchase price |
| ex-div | string (nullable) | ex-dividend date (DD-Mon format) |
| dividend | string (nullable) | dividend amount (e.g. "3.5p") |
| deleted | boolean | soft delete flag (default 0) |

#### `dividend_payments`
| Column | Type | Notes |
|---|---|---|
| id | int (PK, auto) | |
| symbol_id | int | FK to portfolio.id (not enforced in migration) |
| date | string | dividend date |
| amount | integer | dividend amount |

#### `symbols`
| Column | Type | Notes |
|---|---|---|
| id | int (PK, auto) | |
| ticker | string | unique index |
| last-checked | integer | unix timestamp |

#### `phinxlog`
| Phinx migration tracking table | |

---

## 4. Authentication

- **Session-based** (PHP `$_SESSION`) — legacy, still active
- **Token-based** (Bearer in `Authorization` header) — added later
  - 30-day tokens generated on login
  - Tokens validated on each request, session backfilled for compatibility
  - Token cleanup for expired tokens exists but isn't called on a schedule
- Login returns JSON `{ success, token, user_id }`
- Auth filter redirects to `/login` for page requests, returns 401 JSON for API requests

---

## 5. Frontend Architecture

- **No framework** — vanilla JS + jQuery
- **No build step** — raw JS/CSS files served from `/public/assets/`
- HTML generated server-side by PHP string concatenation (no templating engine)
- JS libraries bundled as vendored files: Chart.js v4.4.1, Luxon, Moment.js, ApexCharts, STOMP.js
- Custom "dragonfly" canvas animation (logo mascot) — `dragonfly.js` + `dragonfly-points.js`
- `auth.js` and `common.js` loaded on every page
- A **prototype** exists in `/prototype/` — a modern redesign using:
  - Inter font (Google Fonts)
  - Feather Icons
  - CSS Grid widget layout
  - Glassmorphism/gradient aesthetic
  - Blog feature mockup (not backed by DB)
  - Web Comics section
  - Modals for APOD full view and new blog post

---

## 6. External Dependencies / Integrations

| Service | Usage | Notes |
|---|---|---|
| **Yahoo Finance API** | Stock quotes, OHLC data, weekly ranges | Undocumented v8 API, spoofed iPad User-Agent, 15-min cache |
| **dividenddata.co.uk** | Upcoming dividend scraping | HTML scraping with regex, monthly cache file |
| **NASA APOD** | Astronomy Picture of the Day | Referenced in dashboard/prototype, loaded client-side |
| **SendGrid** | Email | Dependency present, no usage found in code |
| **yt-dlp** | YouTube audio ripping | CLI tool, must be installed in container |
| **STOMP** (RabbitMQ?) | WebSocket chat example | Client-side only reference, server not in this repo |

---

## 7. Deployment / Infrastructure

- Runs in **Docker** (references `host.docker.internal` for DB)
- PHP image lacks PDO by default → needs custom Dockerfile
- yt-dlp must be installed manually in container after restart
- Downloads and cache folders need `www-data` ownership
- `bin/create-db.php` — creates the database
- `bin/create-user.php` — CLI user creation
- Phinx config points at `localhost:3306` (not `host.docker.internal` — inconsistency)
- Reference to `sudo ./50-landscape-sysinfo` for system stats (once/hour max)

---

## 8. Code Quality & Architecture Observations

### Strengths
- Clean PSR-4 autoloading
- Separation of concerns: controllers, services, dashboard area components
- Area interface pattern for dashboard widgets is extensible
- Phinx migrations provide reproducible schema

### Weaknesses
- **No templating engine** — HTML built via string concatenation in PHP classes
- **No dependency injection container** — everything manually wired in `index.php`
- **No error handling** — exceptions largely unhandled, no error pages
- **Security concerns:**
  - Hardcoded DB credentials in `phinx.php` and `bin/*.php` (not using .env)
  - `.env` file contains real credentials (committed to repo?)
  - No CSRF protection
  - No rate limiting on login
  - YouTube ripper does shell `exec()` with user-provided URL (injection risk)
  - Regex-based input sanitisation is fragile
- **Mixed data formats** — dividend amounts stored as strings ("3.5p"), dates as strings ("DD-Mon"), inconsistent
- **Dead code** — Recipe system (stub), chat example (no backend), SendGrid (unused)
- **jQuery dependency** on every page but barely used
- **Vendored JS libraries** — no package management for frontend
- **No tests** of any kind
- **No CSS preprocessor** — raw CSS per feature

---

## 9. What to Keep vs. Rebuild

### Must Keep (database)
All 7 tables and their data. The new app connects to the same `notquitehuman` MySQL database. Migration history (`phinxlog`) should be respected — new migrations should continue the sequence.

### Worth Keeping Conceptually
- The dragonfly canvas animation (charming, personal)
- Dashboard widget pattern (but templated properly)
- Portfolio/dividend tracking functionality
- YouTube ripper (but secured)
- Charts with Yahoo Finance data
- The prototype's visual direction (modern, clean)

### Can Drop
- jQuery (replace with vanilla JS or a lightweight framework)
- Canvas drawing tool (unused experiment?)
- Chat example (no backend, experiment)
- Base64 tool (trivial, could be a browser bookmark)
- STOMP/WebSocket code
- `seniordev.html` (standalone, not part of the app)
- Recipe stub (never built)

### Needs Discussion
- **Blog feature** — mocked in prototype but has no DB tables. Is this wanted in the rebuild?
- **Web Comics section** — in prototype, nice idea. RSS-based update checking?
- **SendGrid** — was email ever needed? Password reset? Notifications?
- **System stats** (`50-landscape-sysinfo`) — keep in dashboard?

---

## 10. Next Steps

Before writing architecture for the rebuild, we should decide:

1. **Tech stack** — Stay PHP? Go Node/TypeScript? Python? Something else entirely?
2. **Frontend** — SPA (React/Vue/Svelte)? Server-rendered? HTMX? The prototype suggests a more interactive UI.
3. **Feature scope** — Which features make the cut for v1 of the rebuild?
4. **Blog** — Is this a real feature you want? Needs DB tables, markdown support, etc.
5. **Authentication** — Keep simple username/password? Add OAuth? Passkeys?
6. **Deployment** — Stay Docker? Move to something else?
7. **Domain** — Is this `notquitehuman.com`? Where's it hosted?

---

*Generated by Rune 🪶 — ready to architect the rebuild whenever you are.*
