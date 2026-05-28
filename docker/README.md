# Doogle Docker Development

This Docker stack runs the current legacy entrypoints from the repository root.
It does not move the application into `public/` yet.

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

## Test

```sh
./docker/test.sh
```

## Stop

```sh
./docker/down.sh
```

To remove the MySQL volume and re-run schema bootstrap:

```sh
./docker/reset-db.sh --force
```
