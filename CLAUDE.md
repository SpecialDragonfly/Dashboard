# CLAUDE.md

## Project Overview

Slim 4 web application.

## Architecture Principles

**Intentionally explicit and low-magic. Do not introduce convenience abstractions without good reason.**

- **All config is plain PHP** — routes, services (PHP-DI), and middleware are each a plain PHP file under `config/`. No YAML, annotations, or attributes.
- **No autowiring** — every constructor dependency is wired by hand in the container config.
- **No ORM** — plain PDO with prepared statements; domain objects are plain PHP classes, hydrated manually by repositories (no reflection). Migrations use Phinx
- **Data flow** — route maps to a controller method, which uses injected services; a service calls a repository, which runs SQL and hydrates a domain object; the controller renders Twig and writes the response.
- **Auth** — bearer-token based, enforced via a PSR-15 middleware applied per-route; tokens are kept client-side in localStorage.
- **Frontend** — vanilla HTML/CSS/JS only, no framework/bundler/jQuery; Feather Icons and Inter font loaded via CDN/Google Fonts.

## Critical Constraints

- The "Days Employed" counter's start date is hardcoded and must not change.
- Database is SQLite by default locally, or MySQL (production, and local MariaDB via docker-compose), switched via an env var — same schema and repository code either way.
- On MySQL, existing tables must never be altered — only additive migrations.
- Avoid engine-specific SQL (SQLite-only or MySQL-only syntax) in repository code, since both engines are live targets — prefer portable patterns.
