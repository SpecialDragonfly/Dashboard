# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Slim 4 rebuild of the NotQuiteHuman web application. The project is in active scaffolding — architecture is locked and documented in `ARCHITECTURE.md`. Read that file first for full context on planned structure, routes, and service wiring before implementing features.

## Commands

```bash
# Install dependencies
composer install

# Start the application
docker-compose up --build

# Access PHP container
docker-compose exec php bash
```

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

### Controllers (PSR-7 signature)
```php
public function action(Request $request, Response $response, array $args): Response
{
    $response->getBody()->write($this->twig->render('template.html.twig', $data));
    return $response;
}
```

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
- **Database is currently SQLite** (`var/data.db`). It will switch to MySQL (`notquitehuman`) before production. Only the PDO DSN in `config/container.php` changes — the schema, migrations, and all repository code stay the same.
- When on MySQL, the existing tables must not be altered — only additive migrations
- New tables added via versioned `.sql` files in `migrations/`, tracked in `migrations_applied` table

## Adding a New Route + Controller

**`config/routes.php`:**
```php
$app->get('/feature', [FeatureController::class, 'index']);
$app->post('/feature', [FeatureController::class, 'create']);
```

**`config/container.php`:**
```php
App\Controller\FeatureController::class => function (ContainerInterface $c) {
    return new FeatureController(
        $c->get(Environment::class),
        $c->get(App\Service\FeatureService::class),
    );
},

App\Service\FeatureService::class => function (ContainerInterface $c) {
    return new FeatureService($c->get(App\Repository\FeatureRepository::class));
},

App\Repository\FeatureRepository::class => function (ContainerInterface $c) {
    return new FeatureRepository($c->get(PDO::class));
},
```

Auth-protected routes chain `->add(TokenAuthMiddleware::class)` in `config/routes.php`:
```php
$app->get('/dashboard', [DashboardController::class, 'index'])->add(TokenAuthMiddleware::class);
$app->post('/feature',  [FeatureController::class, 'create'])->add(TokenAuthMiddleware::class);
```
