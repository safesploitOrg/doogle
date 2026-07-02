# Doogle Docker Development

This Docker stack uses Nginx, PHP-FPM, and MariaDB. Nginx serves only
`public/`, while the PHP-FPM app container mounts the full repository at
`/var/www/html` for Composer, tests, CLI commands, and internal code.

The Docker helper scripts load the repository-level `.env` file when it exists,
even when the script is run from inside the `docker/` directory.
For manual `docker compose` commands, pass `--env-file ../.env` from inside the
`docker/` directory or `--env-file .env` from the repository root.
The helper scripts prefer Docker Compose v2 (`docker compose`) and fall back to
legacy Docker Compose v1 (`docker-compose`) when needed.

## Build

Build the Docker images

```sh
./docker/build.sh
```

## Start

Start the Docker images

```sh
./docker/up.sh
```

To create the initial admin user during startup, pass an admin password through
the environment. The default local username is `admin` and email is
`admin@example.local`; override them with `DOOGLE_ADMIN_USERNAME` and
`DOOGLE_ADMIN_EMAIL`.

```sh
DOOGLE_ADMIN_PASSWORD='change-this-password' ./docker/up.sh
```

To create or confirm an admin after the stack is running:

```sh
DOOGLE_ADMIN_PASSWORD='change-this-password' ./docker/create-admin.sh admin admin@example.local
```

To clear TOTP for an admin that is locked out:

```sh
./docker/admin-reset-totp.sh admin
```

Open:

- Doogle: http://localhost:8000
- phpMyAdmin: http://localhost:8081

Default development credentials without a `.env` override:

- Database: `doogle`
- User: `doogle`
- Password: `doogle`
- Root password: `root`

MariaDB is exposed on host port `3307` by default to avoid clashing with a
local database install. Override ports or passwords with shell environment
variables:

```sh
DOOGLE_APP_PORT=8080 DB_PASSWORD=change-me DB_ROOT_PASSWORD=change-root ./docker/up.sh
```

---

## Test

```sh
./docker/test.sh
```

## Crawl

Run a trusted local/container crawl without a browser session:

```sh
./docker/crawl.sh https://example.com
```

The command still uses the same crawler URL validation and private-network
blocking policy as the authenticated web crawl form.

The authenticated crawl page records crawl history in `crawl_jobs`. Existing
Docker volumes created before crawl jobs or videos existed need the migrations
under `database/migrations/` applied or a local database reset.

The MariaDB migration uses a new `mariadb_data` volume. If you previously used
the old MySQL volume, reset local Docker data before testing the split runtime.

## Security Defaults

- Nginx adds baseline security headers.
- PHP-FPM runs as the non-root `www-data` user.
- Session cookies are HTTP-only, SameSite `Lax`, and can be made secure with
  `SESSION_COOKIE_SECURE=true` when HTTPS is terminated in front of the app.
- Login and crawl POST requests are rate limited by default.
- TOTP verification is rate limited separately when an admin enables it.
- Auth and crawl security events are written to `/var/log/doogle/security.log`
  inside the app container.

## Stop

```sh
./docker/down.sh
```

To remove the MariaDB volume and re-run schema bootstrap:

```sh
./docker/reset-db.sh --force
```
