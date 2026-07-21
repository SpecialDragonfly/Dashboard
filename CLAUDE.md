# CLAUDE.md

## Project Overview

This is a Slim 4 rebuild of the NotQuiteHuman web application. The project is in active scaffolding — architecture is locked and documented in `ARCHITECTURE.md`. Read that file first for full context on planned structure, routes, and service wiring before implementing features.

There is currently no test suite or linter configured.

## Architecture Principles

**This codebase is intentionally explicit and low-magic. Do not introduce convenience abstractions without good reason.**

### All Config is PHP
- Routes: `config/routes.php` — a closure over `Slim\App`
- Services: `config/container.php` — a plain PHP array of closures (PHP-DI definitions)
- Middleware: `config/middleware.php` — a closure over `Slim\App`
- No YAML, no annotations, no attributes

### No Autowiring
- PHP-DI is configured with explicit definitions only — autowiring is not enabled
- Every constructor dependency must be wired by hand in `config/container.php`

### Plain PDO, No ORM
- No Doctrine ORM or DBAL
- Repositories use `PDO` with prepared statements and `PDO::FETCH_ASSOC`
- Domain objects (`src/Domain/`) are plain PHP classes with explicit constructors
- Repositories hydrate domain objects manually — no reflection, no magic

### Data Flow
1. `config/routes.php` → maps URL to `[ControllerClass::class, 'method']`
2. Controller receives injected services (via constructor, wired in `config/container.php`)
3. Controller calls Service → Service calls Repository → Repository runs SQL → hydrates Domain object
4. Controller writes rendered Twig output to `$response->getBody()` and returns `$response`

### Authentication
- Bearer token-based; `TokenAuthMiddleware` (`src/Middleware/TokenAuthMiddleware.php`) is a PSR-15 middleware
- Applied per-route in `config/routes.php` via `->add(TokenAuthMiddleware::class)`
- Tokens stored in localStorage client-side via `auth.js`; `AuthTokenService` manages server-side validation

### Frontend
- Vanilla HTML/CSS/JS — no framework, no bundler, no jQuery
- CSS per-section: `public/assets/css/common.css`, `dashboard.css`, `blog.css`, etc.
- Feather Icons (CDN), Inter font (Google Fonts)

## Critical Constraints

- **Dragonfly logo files** (`public/assets/js/dragonfly.js`, `dragonfly-points.js`) must be copied verbatim from the original codebase. The canvas call `draw(-10, 0, 1.2, 0.75)` must not change.
- **Days Employed counter** start date is hardcoded: **May 28, 2024**
- **Database is SQLite by default** (`var/data.db`, local dev) or **MySQL** (`notquitehuman`, production and local MariaDB via docker-compose), switched via `DB_CONNECTION` in `.env` — see `config/container.php` and `phinx.php`. Same switch, same schema, same repository code either way.
- When on MySQL, the existing tables must not be altered — only additive migrations
- Migrations use **Phinx** (`robmorgan/phinx`), not hand-rolled SQL — PHP migration classes in `db/migrations/`, run via `vendor/bin/phinx migrate`. Phinx's adapter abstraction generates the correct DDL for either engine from one migration file, and tracks applied migrations in its own `phinxlog` table.
- `db/migrations/0001`–`0007` port the real, live production schema 1:1 from the pre-rebuild app's own Phinx migrations (`notquitehuman/db/migrations/`) — these define the tables that already exist in production and must not be altered. `0008` (`blog_posts`) and `0009` (`portfolio_value_history`) are additive, rebuild-only tables with no pre-rebuild equivalent.
- Repository code must match the *real* production column names, which occasionally differ from what you'd guess — e.g. `users.password`/`users.created` (not `password_hash`/`created_at`), `ripped_files.created` (not `created_at`). Don't rename columns to be "more correct"; fix the repository to match what's actually there.
- Avoid SQLite-only or MySQL-only SQL in repository code (e.g. `datetime('now')`, `INSERT OR IGNORE`, `ON CONFLICT ... DO UPDATE`) since both engines are live targets — prefer portable patterns (bind the current timestamp from PHP; try/catch a unique-constraint violation instead of an engine-specific upsert).



