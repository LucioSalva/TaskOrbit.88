# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

TaskOrbit is a private PHP 8.2 + PostgreSQL platform for managing projects, tasks, subtasks, notes and evidence files, with three roles (`GOD`, `ADMIN`, `USER`). It is a hand-rolled MVC framework — **no Composer, no Laravel, no external PHP packages**. All classes are PSR-4 autoloaded under `App\` from `app/` (`public/index.php:55-63`).

## Running the app

The app supports two run modes simultaneously. Both read the same `.env` and the same `public/index.php` front controller — pick whichever the user is currently working with:

### Docker (recommended for new work)
```bash
docker compose up -d --build         # build + start (web + adminer)
docker compose logs -f web           # tail PHP/Apache logs
docker compose exec web bash         # shell into the PHP container
docker compose down                  # stop (keeps storage volume)
docker compose down -v               # stop + wipe storage volume
```
- App URL: `http://localhost:${HOST_HTTP_PORT}${APP_BASE_PATH}` (default `http://localhost:8090/taskorbit`)
- Adminer: `http://localhost:${HOST_ADMINER_PORT}` (default `8091`)
- The `postgres` service in `docker-compose.yml` is **commented out by design** — by default the container connects to the user's real PostgreSQL on the host via `host.docker.internal:5432` (`docker-compose.yml:36-48`). The user has live data there; do not seed a container DB unless explicitly asked.
- Code is bind-mounted (`.:/var/www/html`), so PHP edits are live without rebuild. Only rebuild when changing `docker/Dockerfile`, `docker/php/php.ini`, or `docker/apache/taskorbit.conf`.

### Laragon (legacy, still supported)
- The user historically runs this in Laragon under `localhost/tareas/public/`. `public/.htaccess` is **hardcoded** with `RewriteBase /tareas/public/` for that setup. Do not change it — Docker ignores it via `AllowOverride None` in `docker/apache/taskorbit.conf`.
- A backup of the Laragon `.env` lives at `.env.laragon.bak`.

## Database

- PostgreSQL 14+ with `pgcrypto` extension. Schema in `database/schema.sql`, seeds in `database/seeds.sql`, incremental migrations in `database/migrations/NNN_*.sql`.
- **Migrations are not auto-applied.** Run them manually against the target DB:
  ```bash
  # Against host Postgres (Docker setup, default)
  psql -h localhost -p 5432 -U postgres -d TaskOrbit -f database/migrations/011_indices_dashboard.sql

  # Or from inside the web container
  docker compose exec web bash -c 'PGPASSWORD=$DB_PASSWORD psql -h $DB_HOST -U $DB_USER -d $DB_NAME -f /var/www/html/database/migrations/011_indices_dashboard.sql'
  ```
- All entities use **soft-delete** (`deleted_at TIMESTAMPTZ`). `Usuario::delete` is also soft (sets `activo = FALSE`) to preserve FK integrity in audit logs, projects, tasks, notas, etc. — never physically delete users.
- Notas and Evidencias are **polymorphic**: `notas.scope + referencia_id` and `evidencias.tipo_entidad + entidad_id` point to `proyecto`/`tarea`/`subtarea`. There are no FK constraints on these — integrity is enforced in code.

## Scheduler (cron job)

`bin/scheduler.php` is a CLI script that evaluates 8 notification events (upcoming due, overdue, sin iniciar, sin movimiento, escalation, projects at risk, etc.) and dispatches in-app notifications. It is **idempotent** via dedup windows in `NotificacionService`. Thresholds come from `.env`: `NOTIFY_UPCOMING_DUE_HOURS`, `NOTIFY_SIN_INICIAR_HOURS`, `NOTIFY_INACTIVITY_HOURS`, `NOTIFY_ESCALATION_HOURS`.

```bash
php bin/scheduler.php             # run real notifications
php bin/scheduler.php --dry-run   # only log what would be sent
```

There is no test suite, no linter, no build step — this is plain PHP served by Apache.

## Architecture

### Request lifecycle
1. **Front controller** `public/index.php`: loads `.env` (manual parser, no `vlucas/phpdotenv`), sets security headers including a **strict CSP with per-request nonce** (`CSP_NONCE` constant), starts the session, registers the autoloader and a global exception handler, then dispatches via `App\Core\Router`.
2. **Router** `app/Core/Router.php`: regex-based, supports `{param}` placeholders, middleware groups, and a POST `_method` override for PUT/DELETE. It strips the subpath prefix before matching (see "Subpath routing" below).
3. **Middleware** runs in order — currently `AuthMiddleware` (redirects to `/login`, stashing the intended URL in `$_SESSION['intended']`) and `RoleMiddleware` (instantiated with `new RoleMiddleware(['GOD'])` per route in `routes/web.php`). Admin routes use **double protection**: route-level `RoleMiddleware` AND `requireRole()` inside the controller.
4. **Controllers** extend `App\Core\Controller`, which provides `view()`, `redirect()` (auto-prepends `APP_URL`), `json()` (auto-injects rotated CSRF token only on mutation responses), `flash()`, `requireAuth()`, `requireRole()`, `denyAccess()`, `back()`.
5. **Models** are static classes (no ORM) that talk to PostgreSQL via the `App\Core\Database` PDO singleton (`fetchAll`, `fetchOne`, `execute`, transactions). All SQL is hand-written with prepared statements.
6. **Views** are plain PHP templates rendered via `App\Core\View::render($template, $data, $layout)`. Layouts live in `app/Views/layouts/` (`main`, `auth`). The view is captured into `$content` and injected into the layout.

### Subpath routing (critical)
The app is served under a subpath like `/taskorbit`, not the domain root. Two pieces conspire to make this work:

- **Apache vhost** (`docker/apache/taskorbit.conf`) declares `Alias __APP_BASE_PATH__ /var/www/html/public`. The placeholder is substituted at container startup by `docker/entrypoint.sh` using the `APP_BASE_PATH` env var.
- **Router** (`app/Core/Router.php:87-101`) strips the base path before matching. It **prefers the `APP_BASE_PATH` env var** over `dirname(SCRIPT_NAME)` because under Apache `Alias` the script name does not include the alias prefix. This is why routes in `routes/web.php` are written as `/dashboard`, not `/taskorbit/dashboard`.
- **All redirects and asset URLs go through `APP_URL`** — `Controller::redirect()`, `View::url()`, `View::asset()`. If `APP_URL` does not exactly match `http://HOST:HOST_HTTP_PORT + APP_BASE_PATH`, sessions, redirects, assets, and CSP all break silently.

When adding a new route, write it as `/foo/bar` and never hardcode `/taskorbit/...`.

### Security model
- **CSRF**: `App\Helpers\CSRF` — session-scoped token, no rotation. `CSRF::tokenField()` in every form, `CSRF::verifyRequest()` at the top of every mutating controller action. AJAX gets a fresh token via `_csrf` injected by `Controller::json()` on mutation responses only (never on GET).
- **CSP**: strict nonce-based, **no `unsafe-inline`**. All inline `<script>` and `<style>` blocks must use `nonce="<?= CSP_NONCE ?>"`. Inline event handlers (`onclick=`, `onsubmit=`, etc.) and `javascript:` URLs are forbidden — use `addEventListener` in a nonced script block, or external `js/app.js`. Allowed CDN: `cdn.jsdelivr.net` (Bootstrap + Bootstrap Icons).
- **Login hardening**: `App\Helpers\LoginRateLimiter` (DB-backed via `login_attempts` table from migration `000`) blocks repeated failures per `username + IP`. Wrapped in try/catch — if the table is missing, login still works.
- **Roles**: `GOD` (full admin), `ADMIN`, `USER`. Check via `$this->hasRole('GOD')` or `$this->requireRole('GOD','ADMIN')`. The `usuarios_roles` table enforces one-rol-per-user via `UNIQUE(usuario_id)`.
- **Sensitive directories** (`app/`, `config/`, `database/`, `routes/`, `storage/`, `sql/`, `bin/`, `docker/`) are blocked at the Apache level via `<DirectoryMatch>` in the vhost.

### State machines (estados)
The `estado_tipo` PostgreSQL enum (`por_hacer`, `haciendo`, `terminada`, `enterado`, `ocupado`, `aceptada`) applies to projects, tasks, and subtasks. Transitions and propagation logic live in `App\Services\EstadoService`. The "semáforo" (traffic-light) coloring of tasks/projects is computed by `App\Services\SemaforoService` based on `SEMAFORO_AMARILLO_DIAS`, `SEMAFORO_INACTIVIDAD_HORAS`, `SEMAFORO_INACTIVIDAD_ROJO_HORAS` from `.env`.

### Notifications
`App\Services\NotificacionService` writes to the `notificaciones` table (in-app only — WhatsApp channel was removed in migration `009`). Templates live in `App\Services\NotificacionTemplates`. The cron `bin/scheduler.php` is the only producer of system-generated notifications; user actions (estado changes, comments, assignments) dispatch directly from controllers/services.

### Dashboard graceful degradation
`DashboardController::index` wraps each metric source in its own try/catch with safe defaults and accumulates errors into `$dashboardErrors` passed to the view. **When adding a new metric, follow the same pattern** — a single broken query must not 500 the dashboard.

## Conventions and gotchas

- **Always write SQL by hand with prepared statements via `Database::getInstance()`.** No ORM, no query builder.
- **Avoid N+1 in list views.** When rendering a list of tasks, batch-prefetch related data (evidencias, notas, etc.) into an indexed array before the loop — see `app/Views/tareas/_vista_lista.php` for the pattern using `Evidencia::getByEntidades()`.
- **Soft-delete everywhere.** All `WHERE` clauses must include `deleted_at IS NULL` for whatever entity is being queried.
- **Never use `javascript:` URLs or inline event handlers** — they violate CSP. The login form's password-toggle is the canonical example: `app/Views/auth/login.php:66-78` uses a nonced inline script with `addEventListener`.
- **`APP_URL` must match the browser URL exactly** (scheme, host, port, subpath). Mismatches silently break sessions, CSRF, and asset loading.
- **The user prefers aggressive parallel audits** (qa-production-auditor, appsec-infosec-agent, frontend-php, sql-postgres-analyst) for production-readiness reviews. See `~/.claude/projects/.../memory/feedback_audit_workflow.md`.
