# TaskOrbit — Stack Docker

Replica el comportamiento de Laragon (Apache + PHP + PostgreSQL) en contenedores, con **subpath por proyecto** y puertos configurables para coexistir con otros proyectos en la misma máquina.

## Servicios

| Servicio | Imagen                | Puerto host (default) | Acceso                                 |
|----------|-----------------------|-----------------------|----------------------------------------|
| web      | php:8.2-apache custom | `8080`                | **http://localhost:8080/taskorbit**    |
| postgres | postgres:16-alpine    | `5433`                | `psql -h localhost -p 5433 ...`        |
| adminer  | adminer:latest        | `8081`                | http://localhost:8081                  |

> Los puertos y el subpath se cambian con variables en el `.env` de la raíz: `HOST_HTTP_PORT`, `HOST_DB_PORT`, `HOST_ADMINER_PORT`, `APP_BASE_PATH`, `APP_URL`.

## ¿Por qué subpath y no raíz?

Como en Laragon clásico, donde cada proyecto vive en su propia carpeta (`localhost/miproyecto`), aquí cada stack expone la app bajo un subpath:

- TaskOrbit  → `http://localhost:8080/taskorbit`
- Otro app   → `http://localhost:8090/remtysapp`
- Otro app   → `http://localhost:8100/miproyecto`

Cada stack es independiente, no se pisan, y el `APP_URL` queda explícito.

## Primer arranque

```bash
# 1. Copiar el .env de ejemplo a la RAIZ del proyecto
cp docker/.env.example .env

# 2. (Opcional) Editar puertos / credenciales en .env

# 3. Construir y levantar
docker compose up -d --build

# 4. Abrir
#    App      -> http://localhost:8080/taskorbit
#    Adminer  -> http://localhost:8081  (server: postgres, user: taskorbit)
```

> Si entras a `http://localhost:8080/` (raíz) Apache te redirige automáticamente a `/taskorbit/`.

La primera vez que arranca, Postgres ejecuta automáticamente:

1. `database/schema.sql`
2. `database/seeds.sql`

Las migraciones (`database/migrations/`) **no se aplican automáticamente** — debes ejecutarlas manualmente:

```bash
docker compose exec -T postgres \
  psql -U taskorbit -d taskorbit \
  < database/migrations/010_notas_referencial_integrity.sql

docker compose exec -T postgres \
  psql -U taskorbit -d taskorbit \
  < database/migrations/011_indices_dashboard.sql
```

## Comandos útiles

```bash
# Logs en vivo
docker compose logs -f web
docker compose logs -f postgres

# Shell dentro del contenedor PHP
docker compose exec web bash

# Cliente psql
docker compose exec postgres psql -U taskorbit -d taskorbit

# Reiniciar todo
docker compose restart

# Apagar (mantiene volúmenes)
docker compose down

# Apagar y BORRAR DB + storage (reset total)
docker compose down -v
```

## Multi-proyecto en la misma máquina

Cada proyecto tiene su propio `.env` con su propio puerto y subpath. Ejemplo:

**TaskOrbit** (`.env`):
```env
HOST_HTTP_PORT=8080
APP_BASE_PATH=/taskorbit
APP_URL=http://localhost:8080/taskorbit
```

**Otro proyecto** (`.env`):
```env
HOST_HTTP_PORT=8090
APP_BASE_PATH=/remtysapp
APP_URL=http://localhost:8090/remtysapp
```

Reglas críticas para que no se rompa nada:

1. `APP_BASE_PATH` debe iniciar con `/` y no terminar con `/` (`/taskorbit` ✓, `taskorbit/` ✗).
2. `APP_URL` **debe coincidir exactamente** con `http://HOST:HOST_HTTP_PORT + APP_BASE_PATH`. Si cambias uno, sincroniza el otro.
3. Cada proyecto debe usar puertos distintos (`HOST_HTTP_PORT`, `HOST_DB_PORT`, `HOST_ADMINER_PORT`).
4. Los nombres de contenedor (`taskorbit_web`, `taskorbit_db`, `taskorbit_adminer`) deben ser únicos por proyecto — duplica el `docker-compose.yml` con prefijos distintos si vas a correr varios stacks completos.

> El `APP_URL` es leído por `public/index.php` y usado en cookies, redirects, assets (`View::asset`) y forms. Si no coincide con la URL real del navegador, las sesiones y CSP fallan silenciosamente.

## Cómo funciona el subpath internamente

1. `docker/entrypoint.sh` lee `APP_BASE_PATH` y substituye el placeholder `__APP_BASE_PATH__` en el vhost de Apache antes de arrancar.
2. Apache hace `Alias /taskorbit -> /var/www/html/public`.
3. La regla `RewriteBase /taskorbit/` envía cualquier URL no-archivo a `index.php`.
4. `Router::dispatch()` (`app/Core/Router.php:87-91`) detecta el subpath via `dirname(SCRIPT_NAME)` y lo strippea antes de hacer match con las rutas — por eso las rutas en `routes/web.php` se siguen escribiendo como `/dashboard`, no `/taskorbit/dashboard`.
5. Cuando un controller hace `redirect('/dashboard')`, `Controller::redirect()` (`app/Core/Controller.php:48`) le antepone `APP_URL`, así que el browser termina en `http://localhost:8080/taskorbit/dashboard`.

## Desarrollo

El código del proyecto se monta como bind volume (`.:/var/www/html`), por lo que cualquier cambio en `app/`, `public/`, etc. se refleja al instante sin rebuild. Solo necesitas reconstruir si tocas:

- `docker/Dockerfile`
- `docker/php/php.ini`
- `docker/apache/taskorbit.conf`

```bash
docker compose up -d --build web
```

## Storage de evidencias

`/var/www/html/storage` está montado en un volumen nombrado (`taskorbit_storage`), por lo que las evidencias subidas **persisten** entre reinicios y reconstrucciones, pero se borran con `docker compose down -v`.

## Troubleshooting

| Síntoma | Causa común | Fix |
|---|---|---|
| `bind: address already in use` | Otro proceso usa el puerto | Cambia `HOST_HTTP_PORT` en `.env` |
| `could not connect to server: Connection refused` desde la app | App apunta a `localhost` en lugar del servicio | Verifica `DB_HOST=postgres` en compose |
| 500 al subir evidencia | Permisos en `storage/` | `docker compose exec web chown -R www-data:www-data storage` |
| Cookies de sesión no persisten | `APP_URL` no coincide con la URL del navegador | Sincroniza `APP_URL` con el puerto + subpath real |
| Assets cargan con 404 | `APP_URL` no incluye el subpath | Verifica que `APP_URL=http://localhost:8080/taskorbit` |
| Rutas dan 404 | El subpath del vhost no coincide con `APP_BASE_PATH` | Reconstruye: `docker compose up -d --build web` |
| Redirect loop al entrar | `APP_BASE_PATH` mal formado (sin `/` inicial o con `/` final) | Corrige a `/taskorbit` exacto |
| Schema vacío tras `down -v` | Postgres re-inicializó pero falló al cargar SQL | `docker compose logs postgres` para ver errores de sintaxis |
