# Doogle Refactored

Doogle is a PHP/MySQL search engine and crawler. This branch modernises the
original application incrementally rather than rewriting it.

The current branch has two supported crawl paths:

- authenticated browser crawl at `/crawl.php`
- trusted CLI crawl through `bin/crawl` or `docker/crawl.sh`

Public search remains unauthenticated.

## Current Status

Completed from `ARCHITECTURE2.md`:

- Phase A: Public Web Root
- Phase B: Auth Layer
- Phase C: Login / Logout
- Phase D: Authenticated Web Crawl
- Phase E: CLI Crawl
- Phase F: Legacy Removal
- Phase G: Crawl Jobs / History

Remaining:

- Phase H: Production Hardening

Key changes now in place:

- Composer, PSR-4 autoloading, PHPUnit, PHPStan, PHPCS, and SBOM generation.
- Runtime web files live under `public/`; Docker serves `/var/www/html/public`.
- `app/` contains auth, crawl, database, repository, search, and security classes.
- Legacy root `crawl.php`, root `crawl-manual.php`, and `classes/` have been removed.
- Search uses repositories, services, DTOs, full-text indexes, relevance ranking, and bounded click boost.
- Browser crawling requires an admin login and CSRF token.
- CLI crawling does not require a browser session, but still enforces crawler safety policy.
- Web and CLI crawl jobs are stored in `crawl_jobs` and shown on the authenticated crawl page.

## Quick Start With Docker

Docker is the preferred local workflow.

Start the app and create an initial admin user:

```sh
DOOGLE_ADMIN_PASSWORD='change-this-password' ./docker/up.sh
```

Optional admin overrides:

```sh
DOOGLE_ADMIN_USERNAME=admin \
DOOGLE_ADMIN_EMAIL=admin@example.local \
DOOGLE_ADMIN_PASSWORD='change-this-password' \
./docker/up.sh
```

Open:

- Doogle search: http://localhost:8000
- Admin crawl page: http://localhost:8000/crawl.php
- phpMyAdmin: http://localhost:8081

Default local database details:

- Host from host machine: `localhost:3307`
- Host from app container: `mysql_db:3306`
- Database: `doogle`
- User: `doogle`
- Password: `doogle`
- Root password: `root`

Stop Docker:

```sh
./docker/down.sh
```

Reset the local Docker database volume and re-run schema bootstrap:

```sh
./docker/reset-db.sh --force
```

This deletes local Docker database data.

## Admin Users

Browser crawling requires an admin account.

Create an admin locally:

```sh
DOOGLE_ADMIN_PASSWORD='change-this-password' php bin/create-admin admin admin@example.local
```

Or pass the password as the third argument:

```sh
php bin/create-admin admin admin@example.local change-this-password
```

Create or confirm an admin in a running Docker stack:

```sh
DOOGLE_ADMIN_PASSWORD='change-this-password' ./docker/create-admin.sh admin admin@example.local
```

`docker/up.sh` also creates the initial admin automatically when
`DOOGLE_ADMIN_PASSWORD` is set.

Do not store plaintext passwords in SQL. The command stores a `password_hash()`
value.

## Running Search

Public search is available without login:

```text
http://localhost:8000/
http://localhost:8000/search.php?term=example&type=sites
http://localhost:8000/search.php?term=example&type=images
```

Site search returns 20 results per page. Image search returns 30 results per
page.

## Crawling: Web UI

Use the web UI when you want an authenticated browser workflow and crawl
history.

1. Start Docker:

   ```sh
   DOOGLE_ADMIN_PASSWORD='change-this-password' ./docker/up.sh
   ```

2. Open http://localhost:8000/login.php.

3. Log in with the admin username/password.

4. Open http://localhost:8000/crawl.php.

5. Submit a URL.

The crawl result and recent crawl history are shown on the same page. Web and
CLI crawl history is stored in `crawl_jobs`.

## Crawling: CLI

Use the CLI when you want trusted local/container execution without a browser
session.

Local CLI:

```sh
php bin/crawl https://example.com
```

Docker CLI wrapper:

```sh
./docker/crawl.sh https://example.com
```

Exit codes:

- `0`: crawl completed successfully
- `1`: usage error or crawl failed
- `2`: URL rejected by validation/security policy
- `3`: database/configuration failure

CLI crawling still blocks private/reserved networks by default and still uses
the same crawler limits as the web UI. CLI crawl jobs are recorded with
`requested_by_user_id = NULL` because they are not tied to a browser session.

## Crawler Safety Settings

Crawler settings can be provided through environment variables:

```env
CRAWLER_USER_AGENT=doogleBot/1.0
CRAWLER_MAX_DEPTH=2
CRAWLER_TIMEOUT_SECONDS=10
CRAWLER_MAX_PAGES_PER_JOB=100
CRAWLER_MAX_RESPONSE_BYTES=1048576
CRAWLER_ALLOW_PRIVATE_NETWORKS=false
```

By default, private and reserved network targets such as `127.0.0.1`,
`localhost`, RFC1918 ranges, and link-local ranges are rejected.

## Database And Migrations

Fresh Docker databases are created from `doogle-tables-no-data.sql`.

Existing databases may need migrations:

```text
database/migrations/001_add_search_fulltext_indexes.sql
database/migrations/002_update_users_auth_schema.sql
database/migrations/003_create_crawl_jobs.sql
```

Apply a migration to the Docker database:

```sh
docker compose -f docker/compose.yml exec -T mysql_db mysql -uroot -proot doogle < database/migrations/003_create_crawl_jobs.sql
```

For non-Docker MySQL, apply the same SQL files with your normal MySQL client.

## Local Non-Docker Run

Docker is preferred, but a local PHP/MySQL setup can work.

Minimum expectations:

- PHP `>=8.2`
- Composer
- MySQL-compatible database
- PHP extensions: `pdo`, `pdo_mysql`, `dom`
- web server document root pointed at `public/`

Install dependencies:

```sh
composer install
```

Create `.env` from `.env.example` and set database credentials:

```sh
cp .env.example .env
```

Create the schema:

```sh
mysql -u root -p < doogle-tables-no-data.sql
```

Create an admin:

```sh
DOOGLE_ADMIN_PASSWORD='change-this-password' php bin/create-admin admin admin@example.local
```

Point Apache, nginx, or PHP's local server at `public/`. Example for quick local
testing:

```sh
php -S localhost:8000 -t public
```

The app still expects a configured MySQL-compatible database.

## Development Checks

Run the local quality checks:

```sh
composer validate --strict
composer test
composer analyse
composer lint
composer sbom
```

Run checks in Docker:

```sh
./docker/test.sh
```

The generated SBOM is written to `build/sbom.cdx.json`.

## Repository Layout

Important directories:

```text
app/                  Application classes
bin/                  CLI commands
database/migrations/  Incremental SQL migrations
docker/               Local Docker runtime
public/               Browser document root
tests/                PHPUnit tests
```

Important commands:

```text
bin/create-admin      Create or confirm an admin user
bin/crawl             Run a trusted CLI crawl
docker/up.sh          Start Docker stack
docker/down.sh        Stop Docker stack
docker/crawl.sh       Run CLI crawl inside Docker app container
docker/test.sh        Run Composer checks inside Docker
```

## Notes For Existing Data

If you already have an older Docker volume or database:

- run the migrations above, or
- reset the local Docker database with `./docker/reset-db.sh --force`.

Resetting the Docker database deletes local indexed sites, images, users, and
crawl history.

## Security Defaults

- Browser crawl requires an authenticated admin.
- Browser crawl POST requires CSRF validation.
- CLI crawl is trusted local/container execution, not public HTTP access.
- Private/reserved network crawling is blocked by default.
- Passwords are hashed with `password_hash()`.
- Session IDs are regenerated on login.
- Internal code is outside the web document root.

Production hardening is still Phase H. Before production use, review HTTPS,
secure session cookies, rate limiting, logging, non-root container execution,
and security scanning.
