# Doogle Architecture v2

> **Status:** Post-modernisation architecture source of truth  
> **Audience:** Maintainer, Codex, GitHub Copilot, contributors  
> **Repository:** `safesploitOrg/doogle`  
> **Document purpose:** Define Doogle's next architecture after the initial legacy-modernisation work: authenticated crawling, CLI crawling, public web-root isolation, user/session authentication, legacy code removal, and future platform direction.

---

## 1. Executive Summary

`ARCHITECTURE.md` was created to modernise the original Doogle PHP/MySQL search engine safely without breaking existing behaviour.

That work is now largely complete. Doogle has moved from a learning-era PHP application into a modernised PHP search platform with Composer, PSR-4 autoloading, PHPUnit, PHPStan, repositories, services, DTOs, Docker, SBOM generation, crawler hardening, and improved search ranking.

`ARCHITECTURE2.md` defines the next stage.

The focus is no longer just preserving legacy behaviour. The focus is now to make Doogle safer, cleaner, and more operationally mature by adding:

- a `public/` web root
- authenticated browser-based crawling
- CLI-based crawling
- active use of the existing `users` table
- session authentication
- CSRF protection for crawl actions
- clear legacy file removal
- crawl job/history direction
- improved deployment/security boundaries
- future worker/queue architecture

This document assumes the main modernisation phases from `ARCHITECTURE.md` are complete or close to complete.

---

## 2. Architecture v1 vs Architecture v2

| Area | `ARCHITECTURE.md` | `ARCHITECTURE2.md` |
|---|---|---|
| Main purpose | Modernise legacy app safely | Define post-modernisation platform architecture |
| Primary constraint | Preserve existing behaviour | Add controlled new behaviour safely |
| Crawler | Harden existing crawler | Authenticated web crawler + trusted CLI crawler |
| Web root | Deferred until stable | Move to `public/` |
| Users table | Existing but not central | Used for admin authentication |
| Legacy code | Kept during migration | Identify and remove compatibility layer |
| Runtime | Docker dev runtime | Safer deployable runtime boundary |
| Security focus | SSRF and crawler limits | Auth, CSRF, public root isolation, CLI separation |
| AI/Codex purpose | Modernisation roadmap | Product/platform evolution roadmap |

---

## 3. Scope

### 3.1 In Scope

This architecture covers:

- `public/` web-root migration
- authenticated browser access to crawling
- CLI crawler execution without web login
- user/session authentication
- password hashing and verification
- CSRF protection for web crawl requests
- crawler security policy enforcement in both web and CLI paths
- legacy file/directory classification
- removal plan for legacy compatibility files
- future crawl job/history model
- Docker/runtime updates for the new layout
- Change Docker MySQL to MariaDB
- test strategy for authentication and crawling
- future production-hardening direction

### 3.2 Out of Scope

This architecture does not currently cover:

- full Laravel/Symfony migration
- Kubernetes deployment
- multi-tenant SaaS user management
- replacing MySQL with OpenSearch/Meilisearch immediately
- complex RBAC beyond admin crawl access
- distributed crawler workers as the immediate next step

---

## 4. Target Repository Layout

The next target layout is:

```text
.
├── app/
│   ├── Auth/
│   │   ├── AuthService.php
│   │   ├── SessionAuth.php
│   │   ├── User.php
│   │   └── UserRepository.php
│   ├── Crawl/
│   │   ├── CrawlRequest.php
│   │   ├── CrawlResult.php
│   │   ├── CrawlService.php
│   │   ├── UrlNormalizer.php
│   │   └── UrlValidator.php
│   ├── Database/
│   │   └── ConnectionFactory.php
│   ├── Repository/
│   │   ├── ImageRepository.php
│   │   ├── ImageSearchRepository.php
│   │   ├── SiteRepository.php
│   │   └── SiteSearchRepository.php
│   ├── Search/
│   └── Security/
│       ├── CrawlerSecurityPolicy.php
│       ├── CsrfToken.php
│       └── PrivateNetworkBlocker.php
├── bin/
│   └── crawl
├── database/
│   └── migrations/
├── docker/
│   ├── apache/
│   ├── php/
│   ├── compose.yml
│   ├── crawl.sh
│   ├── test.sh
│   ├── up.sh
│   └── down.sh
├── public/
│   ├── index.php
│   ├── search.php
│   ├── login.php
│   ├── logout.php
│   ├── crawl.php
│   ├── ajax/
│   │   ├── setBroken.php
│   │   ├── updateImageCount.php
│   │   └── updateLinkCount.php
│   └── assets/
├── tests/
│   ├── Integration/
│   └── Unit/
├── ARCHITECTURE.md
├── ARCHITECTURE2.md
├── composer.json
├── composer.lock
├── phpstan.neon
├── phpunit.xml
└── README.md
```

---

## 5. Public Web Root Migration

### 5.1 Goal

The web server document root should be:

```text
/var/www/html/public
```

not:

```text
/var/www/html
```

This prevents accidental web exposure of internal files.

### 5.2 Public Files

Only browser-accessible files should live in `public/`:

```text
public/
├── index.php
├── search.php
├── login.php
├── logout.php
├── crawl.php
├── ajax/
└── assets/
```

### 5.3 Non-Public Files

These must not be web-accessible:

```text
app/
classes/
config.php
config/
database/
docker/
tests/
vendor/
.env
composer.json
composer.lock
phpunit.xml
phpstan.neon
ARCHITECTURE.md
ARCHITECTURE2.md
```

### 5.4 Apache Target

Example Apache configuration:

```apache
DocumentRoot /var/www/html/public

<Directory /var/www/html/public>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

Optional hardening:

```apache
<FilesMatch "^(\.env|composer\.(json|lock)|phpunit\.xml|phpstan\.neon)$">
    Require all denied
</FilesMatch>
```

The main protection should still be the `public/` document root.

---

## 6. Access Model

### 6.1 High-Level Rules

Doogle should have two crawl execution paths:

```text
Browser / HTTP crawl   → authenticated admin session required
CLI crawl              → allowed without web login, trusted local/container execution
Public unauthenticated → blocked from crawling
```

Crawler security controls must always apply, regardless of execution path.

Authentication authorises a user to request a crawl. It must not weaken crawler safety controls.

### 6.2 Route Access Matrix

| Path / Command | Auth Required | Purpose |
|---|---:|---|
| `/` | No | Public search homepage |
| `/search.php` | No | Public site/image search |
| `/ajax/updateLinkCount.php` | No | Click telemetry |
| `/ajax/updateImageCount.php` | No | Image click telemetry |
| `/ajax/setBroken.php` | No | Broken image telemetry |
| `/login.php` | No | Login form |
| `/logout.php` | Yes | Session destruction |
| `/crawl.php` GET | Yes | Admin crawl form |
| `/crawl.php` POST | Yes + CSRF | Submit crawl request |
| `php bin/crawl <url>` | No web auth | Trusted CLI crawl |
| `./docker/crawl.sh <url>` | No web auth | Docker wrapper for CLI crawl |

---

## 7. Authentication Architecture

### 7.1 Goal

The existing `users` table should become active and support admin login for browser-based crawling.

### 7.2 Target Auth Classes

```text
app/Auth/
├── AuthService.php
├── SessionAuth.php
├── User.php
└── UserRepository.php
```

### 7.3 User Model

```php
<?php

declare(strict_types=1);

namespace Doogle\Auth;

final readonly class User
{
    public function __construct(
        public int $id,
        public string $username,
        public string $email,
        public string $role,
    ) {}
}
```

### 7.4 User Repository

Responsibilities:

- find user by username
- optionally find user by ID
- never expose plaintext passwords
- return database rows only to auth service

Target method examples:

```php
public function findByUsername(string $username): ?array;
public function findById(int $id): ?User;
```

### 7.5 Auth Service

Responsibilities:

- verify username/password
- use `password_verify()`
- return `User` on success
- return `null` on failure

Passwords must be stored using:

```php
password_hash($password, PASSWORD_DEFAULT)
```

and verified using:

```php
password_verify($password, $storedHash)
```

### 7.6 Session Auth

Responsibilities:

- start session where needed
- log user in
- regenerate session ID on login
- check whether user is authenticated
- check whether user is admin
- log user out

Session state should store only minimal identity data:

```php
$_SESSION['user'] = [
    'id' => $user->id,
    'username' => $user->username,
    'email' => $user->email,
    'role' => $user->role,
];
```

---

## 8. Users Table Design

### 8.1 Target Schema

```sql
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'admin',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_username (username),
  UNIQUE KEY unique_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 8.2 Rules

- Do not store plaintext passwords.
- Use `password_hash()` for creation.
- Use `password_verify()` for login.
- `role = admin` is required for browser-based crawl access.
- The first admin user can be created manually, via SQL, or via a one-off CLI helper.

### 8.3 Future Optional CLI Admin Creation

Later command:

```bash
php bin/create-admin zepher zepher@example.com
```

This should prompt for a password and insert a hashed password into the users table.

---

## 9. CSRF Protection

### 9.1 Requirement

Authenticated web crawl submissions must require CSRF validation.

Without CSRF protection, a malicious website could cause an authenticated browser to submit a crawl request unintentionally.

### 9.2 Target Class

```text
app/Security/CsrfToken.php
```

Target responsibilities:

- generate token
- store token in session
- verify token on POST
- invalidate/regenerate when appropriate

### 9.3 Crawl Form Requirement

`public/crawl.php` POST must require:

```text
valid authenticated session
valid CSRF token
valid URL
crawler security approval
```

---

## 10. Authenticated Web Crawling

### 10.1 Web Flow

```text
GET /crawl.php
  ↓
start session
  ↓
check authenticated admin
  ↓
show crawl form with CSRF token

POST /crawl.php
  ↓
start session
  ↓
check authenticated admin
  ↓
validate CSRF token
  ↓
validate submitted URL
  ↓
enforce crawler security policy
  ↓
run crawl service
  ↓
show crawl result/status
```

### 10.2 `public/crawl.php` Responsibility

`public/crawl.php` should remain thin.

It should not contain crawler internals. It should:

- load Composer autoload
- start session
- check authentication
- handle GET/POST
- validate CSRF
- call application services
- render form/result

Crawler logic belongs in `app/Crawl/`.

---

## 11. CLI Crawling

### 11.1 Goal

CLI crawling should be trusted local/container execution and should not require web session authentication.

Example:

```bash
php bin/crawl https://example.com
```

Docker wrapper:

```bash
./docker/crawl.sh https://example.com
```

### 11.2 CLI Rules

The CLI command must:

- only run when `PHP_SAPI === 'cli'`
- require a URL argument
- load Composer autoload
- load environment config
- create DB connection through `ConnectionFactory`
- validate the URL
- enforce `CrawlerSecurityPolicy`
- call `CrawlService`
- print useful output
- return sensible exit codes

### 11.3 Exit Codes

| Exit Code | Meaning |
|---:|---|
| `0` | Crawl completed successfully |
| `1` | Usage error or crawl failed |
| `2` | URL rejected by validation/security policy |
| `3` | Database/configuration failure |

---

## 12. Crawler Security Model

### 12.1 Security Rule

Crawler security applies in both browser and CLI modes.

Admin authentication does not bypass crawler controls.

### 12.2 Required Controls

Crawler policy must enforce:

- allow only `http` and `https`
- block localhost
- block private IPv4 ranges
- block link-local ranges
- block IPv6 loopback/link-local/private ranges
- block credentials in URLs
- enforce max depth
- enforce max pages
- enforce timeout
- enforce max response size
- limit redirects
- optionally respect same-host crawling
- optionally respect robots.txt

### 12.3 Blocked Ranges by Default

```text
127.0.0.0/8
10.0.0.0/8
172.16.0.0/12
192.168.0.0/16
169.254.0.0/16
::1/128
fc00::/7
fe80::/10
```

### 12.4 Homelab Exception

Private network crawling may be useful in a homelab, but must be explicit.

Default:

```env
CRAWLER_ALLOW_PRIVATE_NETWORKS=false
```

Only set to true deliberately in a trusted local environment:

```env
CRAWLER_ALLOW_PRIVATE_NETWORKS=true
```

This must never be the default.

---

## 13. Crawl Service Direction

### 13.1 Target Classes

```text
app/Crawl/
├── CrawlRequest.php
├── CrawlResult.php
├── CrawlService.php
├── UrlNormalizer.php
└── UrlValidator.php
```

### 13.2 Crawl Request

Represents a crawl request:

```php
final readonly class CrawlRequest
{
    public function __construct(
        public string $startUrl,
        public int $maxDepth,
        public int $maxPages,
        public int $timeoutSeconds,
        public int $maxResponseBytes,
    ) {}
}
```

### 13.3 Crawl Result

Represents crawl outcome:

```php
final readonly class CrawlResult
{
    public function __construct(
        public bool $successful,
        public int $pagesDiscovered,
        public int $pagesIndexed,
        public int $imagesIndexed,
        public int $urlsRejected,
        public array $errors = [],
    ) {}
}
```

### 13.4 Crawl Service Responsibilities

`CrawlService` should:

- accept a `CrawlRequest`
- validate start URL
- enforce policy
- fetch pages
- parse pages
- index sites/images through repositories
- track limits
- return `CrawlResult`

It should not:

- render HTML
- read raw `$_POST`
- depend on browser sessions
- directly echo debug output

---

## 14. Future Crawl Jobs

### 14.1 Why Crawl Jobs Matter

Browser-triggered synchronous crawling is simple but limited.

Future crawl jobs allow:

- status tracking
- history
- retry behaviour
- async workers
- better observability
- scheduled recrawls

### 14.2 Future Table

```sql
CREATE TABLE crawl_jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  start_url VARCHAR(512) NOT NULL,
  requested_by_user_id INT NULL,
  status ENUM('pending', 'running', 'completed', 'failed', 'rejected') NOT NULL DEFAULT 'pending',
  pages_discovered INT NOT NULL DEFAULT 0,
  pages_indexed INT NOT NULL DEFAULT 0,
  images_indexed INT NOT NULL DEFAULT 0,
  urls_rejected INT NOT NULL DEFAULT 0,
  error_message TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_crawl_jobs_status (status),
  INDEX idx_crawl_jobs_requested_by (requested_by_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 14.3 Future Flow

```text
User submits crawl
  ↓
create crawl_jobs row
  ↓
worker picks job
  ↓
CrawlService runs
  ↓
job status updated
  ↓
admin sees history/status
```

This is not required for the immediate authenticated crawl implementation, but it is the preferred medium-term architecture.

---

## 15. Legacy File Classification

### 15.1 Keep

These are part of the modernised architecture:

```text
app/
database/
docker/
public/
tests/
composer.json
composer.lock
phpstan.neon
phpunit.xml
ARCHITECTURE.md
ARCHITECTURE2.md
README.md
```

### 15.2 Transitional

These may remain temporarily while compatibility is confirmed:

```text
classes/
config.php
crawl.php
crawl-manual.php
```

### 15.3 Remove After Replacement

These should be removed once replacements are proven:

```text
root index.php
root search.php
root ajax/
root assets/
crawl-manual.php
root crawl.php
classes/SiteResultsProvider.php
classes/ImageResultsProvider.php
classes/Crawler.php
classes/DomDocumentParser.php
```

### 15.4 Removal Checks

Before deleting legacy files, run:

```bash
grep -R "classes/" . --exclude-dir=vendor
grep -R "SiteResultsProvider" . --exclude-dir=vendor
grep -R "ImageResultsProvider" . --exclude-dir=vendor
grep -R "DomDocumentParser" . --exclude-dir=vendor
grep -R "new Crawler" . --exclude-dir=vendor
```

Also run:

```bash
composer validate --strict
composer test
composer analyse
composer lint
composer sbom
```

---

## 16. Docker Runtime Updates

### 16.1 Public Root

Docker Apache config should serve:

```text
/var/www/html/public
```

### 16.2 CLI Crawl Wrapper

Add:

```text
docker/crawl.sh
```

Example behaviour:

```bash
#!/usr/bin/env sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
COMPOSE_FILE="$SCRIPT_DIR/compose.yml"

if [ "$#" -lt 1 ]; then
    printf '%s\n' "Usage: ./docker/crawl.sh https://example.com"
    exit 1
fi

docker compose -f "$COMPOSE_FILE" exec app php bin/crawl "$@"
```

### 16.3 Local Workflow

```bash
./docker/up.sh
./docker/test.sh
./docker/crawl.sh https://example.com
./docker/down.sh
```

---

## 17. Testing Strategy v2

### 17.1 New Unit Tests

Add tests for:

```text
AuthServiceTest
SessionAuthTest
UserRepositoryTest
CsrfTokenTest
CrawlRequestTest
CrawlResultTest
CrawlServiceTest
```

### 17.2 Auth Tests

Test:

- valid username/password returns user
- invalid password returns null
- unknown user returns null
- session login stores expected user data
- logout clears session
- admin role check works

### 17.3 CSRF Tests

Test:

- generated token is stored
- valid token passes
- missing token fails
- invalid token fails

### 17.4 Web Crawl Access Tests

Test:

- unauthenticated GET `/crawl.php` blocked or redirected
- unauthenticated POST `/crawl.php` blocked
- authenticated GET shows crawl form
- authenticated POST requires CSRF
- authenticated POST rejects unsafe URL

### 17.5 CLI Crawl Tests

Test:

- no URL argument fails
- invalid URL fails
- private URL fails by default
- valid URL creates crawl request
- CLI does not require session

### 17.6 Security Regression Tests

Both web and CLI crawl paths must reject:

```text
http://127.0.0.1/
http://localhost/
http://10.0.0.1/
http://172.16.0.1/
http://192.168.1.1/
http://169.254.169.254/
file:///etc/passwd
javascript:alert(1)
```

---

## 18. Implementation Phases

### Phase A — Public Web Root

Goal: isolate browser-accessible files.

Tasks:

- create `public/`
- move `index.php` to `public/index.php`
- move `search.php` to `public/search.php`
- move `ajax/` to `public/ajax/`
- move `assets/` to `public/assets/`
- update relative paths
- update Apache document root
- update Docker config

Acceptance criteria:

- homepage loads from `/`
- search works
- AJAX telemetry works
- assets load
- non-public files are not web-accessible
- tests pass

### Phase B — Auth Layer

Goal: make `users` table useful.

Tasks:

- add `app/Auth/User.php`
- add `app/Auth/UserRepository.php`
- add `app/Auth/AuthService.php`
- add `app/Auth/SessionAuth.php`
- add password hashing rules
- add tests

Acceptance criteria:

- valid login succeeds
- invalid login fails
- session state works
- tests pass

### Phase C — Login / Logout

Goal: add minimal admin login.

Tasks:

- add `public/login.php`
- add `public/logout.php`
- add login form
- add session handling
- add basic flash/error output

Acceptance criteria:

- admin can log in
- admin can log out
- failed login does not create session

### Phase D — Authenticated Web Crawl

Goal: protect browser crawling.

Tasks:

- move/create `public/crawl.php`
- require admin session
- add CSRF token
- validate submitted URL
- call crawl service
- render crawl result

Acceptance criteria:

- unauthenticated crawl blocked
- authenticated crawl form works
- invalid CSRF rejected
- unsafe URLs rejected
- valid crawl request works

### Phase E — CLI Crawl

Goal: support trusted CLI crawling.

Tasks:

- add `bin/crawl`
- add `docker/crawl.sh`
- load same app services as web crawl
- enforce same crawler policy
- return useful exit codes

Acceptance criteria:

- `php bin/crawl https://example.com` works
- `./docker/crawl.sh https://example.com` works
- private URLs rejected by default
- no web session required

### Phase F — Legacy Removal

Goal: remove replaced legacy files.

Tasks:

- remove root `index.php`
- remove root `search.php`
- remove root `ajax/`
- remove root `assets/`
- remove `crawl-manual.php`
- remove or redirect root `crawl.php`
- remove unused classes after dependency checks

Acceptance criteria:

- no references to removed files
- tests pass
- Docker app still works
- README updated

### Phase G — Crawl Jobs / History

Goal: make crawling operationally visible.

Tasks:

- add `crawl_jobs` migration
- add `CrawlJobRepository`
- store crawl submissions
- update job status
- show crawl history to admin

Acceptance criteria:

- crawl job created for web request
- job status updated
- history visible to authenticated admin

### Phase H — Production Hardening

Goal: prepare deployable runtime.

Tasks:

- non-root container user
- security headers
- session cookie hardening
- HTTPS assumptions documented
- rate limiting for auth/crawl endpoints
- log crawl/security events
- add CodeQL/Gitleaks/Trivy if not already present

Acceptance criteria:

- secure defaults documented
- CI checks pass
- production risks documented

---

## 19. Security Requirements

### 19.1 General

- Use prepared statements.
- Escape all HTML output.
- Do not commit `.env`.
- Do not commit plaintext credentials.
- Use `password_hash()` and `password_verify()`.
- Regenerate session ID on login.
- Use CSRF protection for state-changing web actions.
- Keep crawler security policy enabled by default.

### 19.2 Session Cookie Settings

Recommended production settings:

```php
session_set_cookie_params([
    'httponly' => true,
    'secure' => true,
    'samesite' => 'Strict',
]);
```

For local HTTP development, `secure` may need to be false.

### 19.3 Crawler Abuse Controls

- max crawl depth
- max pages
- max response size
- timeout
- redirect limit
- private network blocking
- optional same-host restriction
- optional rate limiting

---

## 20. Documentation Updates Required

Update `README.md` to prefer the new flow:

```bash
./docker/up.sh
./docker/test.sh
./docker/crawl.sh https://example.com
./docker/down.sh
```

Document:

- public search usage
- admin login
- crawl form
- CLI crawling
- creating first admin user
- `.env` crawler settings
- Docker ports
- security defaults

Update `ARCHITECTURE.md` to state that the legacy-modernisation phase has been superseded by this v2 architecture for future functionality.

---

## 21. Definition of Done

A v2 task is done when:

- implementation matches this architecture
- tests are added or updated
- Composer tests pass
- PHPStan passes
- PHPCS passes
- Docker workflow works
- README is updated
- no secrets are committed
- unsafe crawler targets remain blocked
- browser crawl requires authentication
- CLI crawl works without web session

---

## 22. Target End State

The desired v2 end state is:

```text
Doogle
  |
  +-- public search engine UI
  +-- public image/site search
  +-- authenticated admin crawl UI
  +-- trusted CLI crawl command
  +-- active users table
  +-- session authentication
  +-- CSRF-protected crawl requests
  +-- safe bounded crawler
  +-- public/ web root isolation
  +-- modern app/ architecture
  +-- Docker local runtime
  +-- CI quality gates
  +-- SBOM generation
  +-- legacy files removed
```

This preserves the original spirit of Doogle while turning it into a safer and more credible DevSecOps/platform engineering project.
