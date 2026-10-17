# Gr8r Studio backend

Symfony 8.1 and PostgreSQL 17. The API speaks the frontend's own data shape: `GET /api/bootstrap`
returns the visible Workspace as the frontend's `S.data`, and `POST /api/sync` applies an ordered
batch of writes in one transaction (see `docs/adr/` and `CONTEXT.md` in the repository root).

Requirements: PHP 8.4 with `pdo_pgsql`, Composer 2, Docker with Compose.

## Run it

All commands run in `backend/`.

```sh
composer install

# PostgreSQL 17 on host port 5433 (container "gr8r-db")
docker compose up -d --wait

# Database and schema (dev)
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate -n

# Demo Workspace "Gr8r Studio", dated relative to today. Re-running replaces it with a fresh copy.
php bin/console app:demo:seed

# API on http://127.0.0.1:8000 (either one)
symfony serve --no-tls --port=8000
php -S 127.0.0.1:8000 -t public
```

To use it from the app, run the frontend with `VITE_API_URL=/api npm run dev` in the repository
root (see "Backend mode" in the root README).

Sign in as `hello@gr8rstudio.com` (the Owner) or any other active demo Member, such as
`sarah@gr8rstudio.com`, with the password `gr8r-studio-demo`:

```sh
curl -c cookies.txt -H 'Content-Type: application/json' \
  -d '{"email":"hello@gr8rstudio.com","password":"gr8r-studio-demo"}' http://127.0.0.1:8000/api/login
curl -b cookies.txt http://127.0.0.1:8000/api/bootstrap
```

## Tests

PHPUnit runs against the real PostgreSQL test database (`gr8r_test`); every test runs in a transaction
that is rolled back (dama/doctrine-test-bundle).

```sh
php bin/console doctrine:database:create --env=test --if-not-exists
php bin/console doctrine:migrations:migrate --env=test -n
php bin/phpunit
```

## Endpoints

| Endpoint              | Purpose                                                             |
| --------------------- | ------------------------------------------------------------------- |
| `GET /api/health`     | `{"status":"ok"}`, no sign-in needed                                |
| `POST /api/login`     | `{email, password}` → `{user:{email,name}, member}` + session cookie |
| `POST /api/logout`    | `204`, ends the session                                             |
| `GET /api/me`         | same body as login, or `401`                                        |
| `GET /api/bootstrap`  | `{data, prefs, views}` for the signed-in Member                     |
| `POST /api/sync`      | `{"ops": [{op, type, id?, data?}]}` → `{"ok": true}`, `422` or `403` |

Errors answer as `{"error": "<code>", "message": "…"}`; without a session every endpoint except
health and login answers `401 {"error":"unauthenticated"}`.

## Where things live

- `src/Snapshot/Snapshot.php`: what a Member sees of their Workspace, in the frontend's shape.
- `src/Sync/Sync.php`: applies a batch in one transaction. One handler per collection or singleton
  in `src/Sync/Handler/`; a new synced collection (`put`/`delete`) is one new class implementing
  `App\Sync\CollectionHandler`, a new singleton (`set`, like `projOrder`) one implementing
  `App\Sync\SingletonHandler`. When a new Task brings a key already taken in its Project, the
  server renumbers it and the answer says so: `{"ok": true, "keys": {"<taskId>": "WEB-207"}}`.
- `src/Access/ProjectAccess.php`: who sees and changes which Projects (Private projects, Guests);
  used by the snapshot and by every handler.
- `src/Demo/DemoSeed.php`: the demo Workspace, ported from `src/data/seed.js`.
