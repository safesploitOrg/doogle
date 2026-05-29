# Doogle Docker Development

This Docker stack serves the application from `public/` while mounting the full
repository at `/var/www/html` for Composer, tests, and internal code.

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

Open:

- Doogle: http://localhost:8000
- phpMyAdmin: http://localhost:8081

Default development credentials:

- Database: `doogle`
- User: `doogle`
- Password: `doogle`
- Root password: `root`

MySQL is exposed on host port `3307` by default to avoid clashing with a local
MySQL install. Override ports or passwords with shell environment variables:

```sh
DOOGLE_APP_PORT=8080 DOOGLE_DB_PASSWORD=change-me ./docker/up.sh
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

## Stop

```sh
./docker/down.sh
```

To remove the MySQL volume and re-run schema bootstrap:

```sh
./docker/reset-db.sh --force
```
