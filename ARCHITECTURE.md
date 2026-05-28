# Doogle Architecture

> **Progress** 
> **Status:** Source of truth for modernising Doogle  
> **Audience:** Maintainer, Codex, GitHub Copilot, contributors  
> **Repository:** `safesploitOrg/doogle`  
> **Document purpose:** Define the current system, target architecture, testing strategy, CI/CD path, SBOM/security controls, and phased implementation plan.

---

## 1. Executive Summary

Doogle is a PHP/MySQL search engine and web crawler. It crawls websites, extracts page metadata and images, stores indexed records in MySQL, and provides search pages for website and image results.

The current application is functional but structured as a learning-era PHP application. It mixes application logic, HTML rendering, database access, crawler behaviour, and global state. This makes automated testing, CI/CD, dependency management, SBOM generation, static analysis, and secure crawling harder than they need to be.

The modernisation goal is **not a full rewrite**. The preferred approach is an incremental refactor that keeps existing behaviour while introducing:

- Composer dependency management
- PSR-4 autoloading
- PHPUnit or Pest tests
- service/repository separation
- safer crawler boundaries
- environment-based configuration
- Docker-friendly runtime
- GitHub Actions CI/CD
- SAST and dependency scanning
- CycloneDX SBOM generation

This project should be treated as a **legacy PHP modernisation + DevSecOps hardening case study**.

---

## 2. Current-State Architecture

### 2.1 Current Application Responsibilities

Doogle currently provides:

- Homepage search interface
- Website result search
- Image result search
- Web crawler entrypoint
- Manual crawler entrypoint
- MySQL-backed index storage
- AJAX endpoints for click/broken-image updates
- Basic click-based result ordering

### 2.2 Current Repository Layout

Current high-level layout:

```text
.
├── ajax/
│   ├── setBroken.php
│   ├── updateImageCount.php
│   └── updateLinkCount.php
├── assets/
│   ├── css/
│   ├── images/
│   └── js/
├── classes/
│   ├── Crawler.php
│   ├── DomDocumentParser.php
│   ├── ImageResultsProvider.php
│   └── SiteResultsProvider.php
├── config.php
├── crawl.php
├── crawl-manual.php
├── doogle-tables-no-data.sql
├── index.php
├── search.php
└── README.md
```

### 2.3 Current Runtime Flow

```text
User
  |
  v
index.php
  |
  v
search.php
  |
  +--> SiteResultsProvider.php  ---> MySQL sites table
  |
  +--> ImageResultsProvider.php ---> MySQL images table
                                  |
                                  +--> AJAX click updates
                                  +--> AJAX broken-image updates

Crawler flow:

crawl.php / crawl-manual.php
  |
  v
Crawler.php
  |
  v
DomDocumentParser.php
  |
  +--> extract links
  +--> extract title/meta fields
  +--> extract images
  |
  v
MySQL sites/images tables
```

### 2.4 Current Database Model

Current logical database entities:

```text
sites
  id
  url
  title
  description
  keywords
  clicks

images
  id
  siteUrl
  imageUrl
  alt
  title
  clicks
  broken

users
  id
  username
  email
  password
```

Notes:

- `sites` stores indexed pages.
- `images` stores indexed images associated with a source page.
- `users` exists in the SQL schema but does not appear central to current search/crawl functionality.
- The database currently relies primarily on primary keys, with limited visible domain-specific constraints.
- A future migration should add uniqueness constraints on `sites.url` and `images.imageUrl`.

### 2.5 Current Strengths

The current project already demonstrates useful application behaviour:

- real crawler/indexer behaviour
- metadata extraction
- image extraction
- MySQL persistence
- result pagination
- image preview/loading behaviour
- click-based ranking
- basic broken image handling
- OOP classes for result providers and parser/crawler concepts

### 2.6 Current Architecture Limitations

The current architecture has several constraints:

| Area | Current Issue | Impact |
|---|---|---|
| Dependency management | No Composer foundation | Harder to add PHPUnit, SAST, SBOM, libraries |
| Autoloading | Manual includes/requires | Harder refactoring and namespace hygiene |
| Testing | No clear test seams | Unit testing is difficult |
| Database access | SQL inside provider/crawler classes | Business logic and persistence are coupled |
| HTML rendering | HTML built inside PHP service classes | Difficult to test and maintain |
| Crawler state | Uses global arrays/state | Hard to reason about and test |
| Security | Arbitrary URL fetching risk | SSRF and internal network exposure risk |
| Configuration | `config.php`-style config | Secrets/config not environment-driven |
| Observability | Debug `echo` output | No structured logs or job state |
| CI/CD | Not yet first-class | No automated quality gate |

---

## 3. Target Architecture Principles

The target architecture should follow these principles:

1. **Modernise incrementally, do not rewrite blindly.**
2. **Preserve current user-facing behaviour first.**
3. **Introduce Composer before major refactors.**
4. **Separate logic from persistence and presentation.**
5. **Make crawler security a first-class concern.**
6. **Make every important behaviour testable.**
7. **Prefer small pull requests over large rewrites.**
8. **Use CI as the quality gate.**
9. **Generate SBOMs from real dependency manifests.**
10. **Keep the project useful as a DevSecOps portfolio artefact.**

---

## 4. Target Repository Layout

Recommended modernised layout:

```text
.
├── app/
│   ├── Crawl/
│   │   ├── Crawler.php
│   │   ├── CrawlJob.php
│   │   ├── CrawlQueue.php
│   │   ├── HtmlDocumentParser.php
│   │   ├── UrlNormalizer.php
│   │   ├── UrlValidator.php
│   │   └── RobotsPolicy.php
│   ├── Database/
│   │   ├── ConnectionFactory.php
│   │   └── MigrationRunner.php
│   ├── Http/
│   │   ├── Controller/
│   │   │   ├── SearchController.php
│   │   │   ├── ImageSearchController.php
│   │   │   └── AjaxController.php
│   │   └── Request.php
│   ├── Repository/
│   │   ├── SiteRepository.php
│   │   └── ImageRepository.php
│   ├── Search/
│   │   ├── SearchService.php
│   │   ├── ImageSearchService.php
│   │   ├── SearchResult.php
│   │   ├── ImageResult.php
│   │   └── Paginator.php
│   ├── Security/
│   │   ├── CrawlerSecurityPolicy.php
│   │   └── PrivateNetworkBlocker.php
│   └── View/
│       ├── Renderer.php
│       └── templates/
├── bin/
│   └── doogle
├── config/
│   └── app.php
├── database/
│   ├── migrations/
│   └── schema.sql
├── docker/
│   ├── nginx/
│   └── php/
├── public/
│   ├── index.php
│   ├── search.php
│   ├── ajax/
│   └── assets/
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── Feature/
├── .env.example
├── composer.json
├── composer.lock
├── docker-compose.yml
├── phpstan.neon
├── phpunit.xml
├── psalm.xml
└── README.md
```

### 4.1 Migration Approach for Layout

Do not move everything at once. Use this order:

1. Add Composer and PSR-4 autoloading.
2. Keep legacy entrypoints working.
3. Move classes one at a time into `app/`.
4. Add compatibility wrappers only where needed.
5. Move public web files into `public/` after routing/config is stable.
6. Add tests as each class is extracted.

---

## 5. Composer and Autoloading

### 5.1 Composer Requirement

Composer should become mandatory for the modernised project.

Initial `composer.json` target:

```json
{
  "name": "safesploit/doogle",
  "description": "A PHP/MySQL search engine and crawler modernised with DevSecOps practices.",
  "type": "project",
  "license": "MIT",
  "autoload": {
    "psr-4": {
      "Doogle\\": "app/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "Doogle\\Tests\\": "tests/"
    }
  },
  "require": {
    "php": ">=8.2",
    "ext-pdo": "*",
    "ext-dom": "*",
    "vlucas/phpdotenv": "^5.6",
    "monolog/monolog": "^3.0"
  },
  "require-dev": {
    "phpunit/phpunit": "^11.0",
    "phpstan/phpstan": "^1.10",
    "squizlabs/php_codesniffer": "^3.9",
    "cyclonedx/cyclonedx-php-composer": "^4.0"
  },
  "scripts": {
    "test": "phpunit",
    "analyse": "phpstan analyse app tests",
    "lint": "phpcs app tests --standard=PSR12",
    "sbom": "cyclonedx-php-composer make-sbom --output-file build/sbom.cdx.json"
  }
}
```

### 5.2 Namespace Standard

All new PHP classes should use the `Doogle\` namespace.

Example:

```php
<?php

declare(strict_types=1);

namespace Doogle\Search;

final class Paginator
{
    public function offset(int $page, int $pageSize): int
    {
        return max(0, ($page - 1) * $pageSize);
    }
}
```

---

## 6. Configuration Architecture

### 6.1 Current Problem

The current application uses PHP config directly. This makes local/dev/prod separation and CI secrets handling harder.

### 6.2 Target Configuration

Use `.env` for environment values and commit only `.env.example`.

Example `.env.example`:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8080

DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=doogle
DB_USERNAME=doogle
DB_PASSWORD=change-me

CRAWLER_USER_AGENT=doogleBot/1.0
CRAWLER_MAX_DEPTH=2
CRAWLER_TIMEOUT_SECONDS=10
CRAWLER_MAX_PAGES_PER_JOB=100
CRAWLER_ALLOW_PRIVATE_NETWORKS=false
```

### 6.3 Configuration Rules

- Never commit real secrets.
- Keep defaults safe.
- Treat `CRAWLER_ALLOW_PRIVATE_NETWORKS=false` as the secure default.
- Keep database credentials environment-specific.
- CI should use GitHub Actions secrets or service containers.

---

## 7. Database Architecture

### 7.1 Target Tables

Short-term schema should keep existing tables but improve constraints.

Recommended `sites`:

```sql
CREATE TABLE sites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  url VARCHAR(512) NOT NULL,
  title VARCHAR(512) NOT NULL,
  description TEXT NULL,
  keywords TEXT NULL,
  clicks INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_site_url (url),
  FULLTEXT KEY ft_sites_search (title, description, keywords, url)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Recommended `images`:

```sql
CREATE TABLE images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_url VARCHAR(512) NOT NULL,
  image_url VARCHAR(512) NOT NULL,
  alt VARCHAR(512) NULL,
  title VARCHAR(512) NULL,
  clicks INT NOT NULL DEFAULT 0,
  broken TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_image_url (image_url),
  KEY idx_images_site_url (site_url),
  FULLTEXT KEY ft_images_search (title, alt, image_url)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 7.2 Migration Strategy

Use SQL migration files:

```text
database/migrations/
├── 001_create_sites_table.sql
├── 002_create_images_table.sql
├── 003_add_fulltext_indexes.sql
└── 004_add_crawl_jobs_table.sql
```

### 7.3 Future Crawl Job Tables

For queue-based crawling:

```sql
CREATE TABLE crawl_jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  start_url VARCHAR(512) NOT NULL,
  status ENUM('pending', 'running', 'completed', 'failed') NOT NULL DEFAULT 'pending',
  pages_discovered INT NOT NULL DEFAULT 0,
  pages_indexed INT NOT NULL DEFAULT 0,
  error_message TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 8. Application Layer Design

### 8.1 Search Layer

Target classes:

```text
Doogle\Search\SearchService
Doogle\Search\ImageSearchService
Doogle\Search\Paginator
Doogle\Repository\SiteRepository
Doogle\Repository\ImageRepository
```

Responsibility split:

| Component | Responsibility |
|---|---|
| `SearchService` | Search business rules |
| `ImageSearchService` | Image search business rules |
| `Paginator` | Page/offset calculations |
| `SiteRepository` | Database queries for sites |
| `ImageRepository` | Database queries for images |
| View/template | HTML rendering |

### 8.2 Search Service Example

```php
<?php

declare(strict_types=1);

namespace Doogle\Search;

use Doogle\Repository\SiteRepository;

final class SearchService
{
    public function __construct(
        private readonly SiteRepository $sites,
        private readonly Paginator $paginator,
    ) {}

    public function search(string $term, int $page, int $pageSize): SearchPage
    {
        $term = trim($term);

        if ($term === '' || mb_strlen($term) < 2) {
            return SearchPage::empty($page, $pageSize);
        }

        $offset = $this->paginator->offset($page, $pageSize);

        return new SearchPage(
            results: $this->sites->search($term, $offset, $pageSize),
            total: $this->sites->countBySearchTerm($term),
            page: $page,
            pageSize: $pageSize,
        );
    }
}
```

### 8.3 Repository Example

```php
<?php

declare(strict_types=1);

namespace Doogle\Repository;

use PDO;

final class SiteRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function countBySearchTerm(string $term): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS total
             FROM sites
             WHERE title LIKE :term
                OR url LIKE :term
                OR keywords LIKE :term
                OR description LIKE :term'
        );

        $like = '%' . $term . '%';
        $stmt->bindValue(':term', $like);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }
}
```

---

## 9. Crawler Architecture

### 9.1 Current Crawler Risks

The crawler fetches user-provided URLs. This introduces serious risks if deployed in a real network:

- SSRF into private services
- accidental crawling of internal infrastructure
- infinite or excessive recursion
- crawling non-HTTP resources
- long timeouts/hanging requests
- duplicate crawl loops
- no robots policy
- no clear job boundaries

### 9.2 Target Crawler Components

```text
Doogle\Crawl\Crawler
Doogle\Crawl\HtmlDocumentParser
Doogle\Crawl\UrlNormalizer
Doogle\Crawl\UrlValidator
Doogle\Crawl\CrawlQueue
Doogle\Security\CrawlerSecurityPolicy
Doogle\Security\PrivateNetworkBlocker
```

### 9.3 Crawler Security Policy

Default rules:

- allow only `http` and `https`
- block localhost
- block private IPv4 ranges
- block link-local addresses
- block IPv6 loopback/link-local/private ranges
- block credentials in URLs
- enforce max depth
- enforce max pages per job
- enforce timeout
- enforce max response size
- optionally enforce same-host crawling
- optionally respect `robots.txt`

Private ranges to block by default:

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

This matters for homelab/cloud safety.

### 9.4 URL Validator Example Behaviour

```text
Input:  https://example.com/page
Output: allowed

Input:  file:///etc/passwd
Output: rejected

Input:  http://127.0.0.1/admin
Output: rejected

Input:  http://172.16.0.1/
Output: rejected by default

Input:  javascript:alert(1)
Output: rejected
```

### 9.5 Crawl Queue Direction

Short term:

- keep synchronous crawling but encapsulate state in classes
- remove global arrays
- add max-depth and max-pages controls

Medium term:

- introduce `crawl_jobs` table
- add CLI worker: `php bin/doogle crawl:run`
- avoid browser-triggered long-running crawls

Long term:

- use Redis or database queue
- add scheduled crawls
- add crawl status UI

---

## 10. Presentation Layer

### 10.1 Current Problem

Current result provider classes generate HTML strings directly. This mixes application logic with presentation and makes tests brittle.

### 10.2 Target Direction

Result providers/services should return data objects, not HTML.

Example result DTO:

```php
final readonly class SearchResult
{
    public function __construct(
        public int $id,
        public string $url,
        public string $title,
        public ?string $description,
        public int $clicks,
    ) {}
}
```

Templates should render DTOs.

### 10.3 Front Controller Direction

Eventually use `public/index.php` as the main web entrypoint.

Routes can remain simple at first:

```text
/search?term=linux&type=sites&page=1
/search?term=linux&type=images&page=1
/ajax/link-click
/ajax/image-click
/ajax/image-broken
```

A full framework is not required initially.

---

## 11. Testing Strategy

### 11.1 Testing Goal

Testing should support safe refactoring and CI/CD. The first goal is not 100% coverage. The first goal is protecting critical behaviour.

### 11.2 Test Types

```text
Unit tests
  - fast
  - no database
  - no network
  - test isolated logic

Integration tests
  - use MySQL service container
  - test repositories and migrations

Feature tests
  - test search endpoints or CLI commands
  - limited number only
```

### 11.3 High-Value Unit Tests

Start with these:

| Area | Test |
|---|---|
| Pagination | page 1 offset = 0 |
| Pagination | page 2 offset = page size |
| Pagination | invalid page normalisation |
| URL normalisation | relative URL to absolute URL |
| URL validation | reject `javascript:` |
| URL validation | reject localhost/private IPs |
| Search service | empty search returns no results |
| Search service | short terms are rejected/normalised |
| Image search | broken images excluded |
| Field trimming | long titles/descriptions trimmed safely |

### 11.4 High-Value Integration Tests

Use MySQL in CI:

- schema applies successfully
- site insert works
- duplicate site URL rejected
- image insert works
- duplicate image URL rejected
- site search returns expected records
- image search excludes broken images
- click count update increments correctly

### 11.5 Tests Not Worth Prioritising Initially

Avoid spending early effort testing:

- third-party library internals
- raw PDO itself
- browser layout details
- every HTML string exactly
- live crawling of external websites

### 11.6 PHPUnit Configuration

Recommended `phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>app</directory>
        </include>
    </source>
</phpunit>
```

---

## 12. CI/CD Architecture

### 12.1 CI Goals

Every pull request should prove:

- dependencies install cleanly
- unit tests pass
- integration tests pass where applicable
- code style is acceptable
- static analysis passes
- SBOM can be generated
- dependency vulnerabilities are surfaced

### 12.2 Initial GitHub Actions Workflow

- NOTE: TODO: refactor MySQL to MariaDB

```yaml
name: CI

on:
  push:
    branches: [ main ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.4
        env:
          MYSQL_DATABASE: doogle_test
          MYSQL_USER: doogle
          MYSQL_PASSWORD: doogle
          MYSQL_ROOT_PASSWORD: root
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping -h 127.0.0.1 -uroot -proot"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=5

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: pdo, pdo_mysql, dom, mbstring
          coverage: none

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Run unit tests
        run: composer test

      - name: Run static analysis
        run: composer analyse

      - name: Run code style checks
        run: composer lint

      - name: Generate SBOM
        run: mkdir -p build && composer sbom

      - name: Upload SBOM
        uses: actions/upload-artifact@v4
        with:
          name: doogle-sbom
          path: build/sbom.cdx.json
```

### 12.3 Future CI Enhancements

Add later:

- CodeQL
- Dependabot
- Trivy filesystem/container scanning
- OWASP ZAP baseline scan against local container
- Docker image build
- GitHub container registry publishing
- release artefacts

---

## 13. SBOM and Supply Chain Design

### 13.1 SBOM Goal

The SBOM should provide visibility into PHP dependencies and later container/image dependencies.

### 13.2 Composer SBOM

Use CycloneDX for Composer dependencies:

```bash
composer require --dev cyclonedx/cyclonedx-php-composer
composer sbom
```

Output:

```text
build/sbom.cdx.json
```

### 13.3 Container SBOM Later

When Docker images are introduced:

```bash
trivy image --format cyclonedx --output build/container-sbom.cdx.json doogle:latest
```

### 13.4 Supply Chain Controls

Recommended controls:

- commit `composer.lock`
- enable Dependabot for Composer and GitHub Actions
- pin major versions in workflows
- review transitive dependencies
- avoid unnecessary packages
- run `composer audit` in CI

---

## 14. Security Architecture

### 14.1 Main Security Concerns

| Concern | Risk |
|---|---|
| SSRF | crawler accesses internal services |
| Stored XSS | indexed page metadata rendered unsafely |
| SQL injection | dynamic SQL mistakes during refactor |
| Credential exposure | config/secrets committed accidentally |
| Dependency risk | untracked third-party packages |
| Abuse | crawler can be used to hit third-party sites |
| Broken access control | future admin/crawl features exposed |

### 14.2 Secure Defaults

- Escape all HTML output.
- Use prepared statements only.
- Block private networks in crawler by default.
- Set request timeouts.
- Limit crawl depth and page count.
- Do not expose crawl controls publicly without auth.
- Keep secrets out of Git.
- Use security headers in NGINX/Apache.

### 14.3 Output Escaping Rule

Anything from indexed websites must be treated as untrusted.

Escape on output:

```php
htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
```

### 14.4 Crawler Abuse Control

Crawler controls should include:

- max pages per crawl
- max depth
- max response size
- timeout
- allowed schemes
- private network blocking
- optional domain allowlist
- rate limiting between requests
- user-agent string
- robots.txt support where feasible

---

## 15. Docker and Runtime Architecture

### 15.1 Target Local Development Stack

```text
Browser
  |
  v
NGINX / Apache container
  |
  v
PHP-FPM container
  |
  v
MySQL container
```

### 15.2 Suggested Services

```text
app      PHP-FPM runtime
web      NGINX serving public/
mysql    MySQL 8.x database
worker   optional crawler worker
```

### 15.3 Development Bind Mounts

Use bind mounts for local development so code changes do not require image rebuilds.

```yaml
services:
  app:
    volumes:
      - .:/var/www/html
```

For production-like builds, copy code into the image instead.

---

## 16. Logging and Observability

### 16.1 Current State

Crawler/debug output is mostly direct `echo` output.

### 16.2 Target State

Use structured logs through Monolog.

Log events:

- crawl started
- crawl completed
- URL rejected
- URL fetched
- URL indexed
- image indexed
- duplicate skipped
- database error
- security policy rejection

Example log fields:

```json
{
  "event": "crawler.url_rejected",
  "url": "http://127.0.0.1/admin",
  "reason": "private_network_blocked"
}
```

---

## 17. Codex Modernisation Plan

Codex should implement changes in small, reviewable phases.

### Phase 0 — Baseline

Goal: document current behaviour before changing it.

Tasks:

- add `ARCHITECTURE.md`
- add `.gitignore` improvements if needed
- confirm app runs locally
- record current setup in README

Acceptance criteria:

- no behaviour change
- documentation committed

### Phase 1 — Composer Foundation

Goal: introduce Composer without changing behaviour.

Tasks:

- add `composer.json`
- add `vendor/` to `.gitignore`
- add PSR-4 autoload mapping
- add PHPUnit as dev dependency
- add `tests/Unit` directory
- add basic smoke test

Acceptance criteria:

- `composer install` works
- `composer test` works
- current pages still load

### Phase 2 — Extract Pure Utility Logic

Goal: create first meaningful unit-testable code.

Tasks:

- extract URL normalisation into `Doogle\Crawl\UrlNormalizer`
- extract pagination into `Doogle\Search\Paginator`
- extract field trimming into a formatter/helper
- add unit tests

Acceptance criteria:

- URL normalisation tests pass
- pagination tests pass
- legacy behaviour preserved

### Phase 3 — Database Connection Factory

Goal: remove direct config coupling gradually.

Tasks:

- add `.env.example`
- add `ConnectionFactory`
- keep `config.php` compatibility if needed
- update entrypoints to use factory

Acceptance criteria:

- local DB connection works
- tests can use test DB config

### Phase 4 — Repository Layer

Goal: separate SQL from HTML rendering.

Tasks:

- create `SiteRepository`
- create `ImageRepository`
- move count/search/update queries into repositories
- add integration tests with MySQL

Acceptance criteria:

- search results unchanged
- repository tests pass

### Phase 5 — Service Layer

Goal: move business logic out of page/provider classes.

Tasks:

- create `SearchService`
- create `ImageSearchService`
- return DTOs instead of HTML
- keep templates simple

Acceptance criteria:

- services unit tested
- UI output remains equivalent

### Phase 6 — Crawler Hardening

Goal: make crawling safe and bounded.

Tasks:

- create `UrlValidator`
- create `CrawlerSecurityPolicy`
- block private networks by default
- add max depth
- add max pages per job
- add request timeout
- add unit tests for rejected URLs

Acceptance criteria:

- private network URLs rejected by default
- crawler cannot recurse indefinitely
- tests pass

### Phase 7 — CI Pipeline

Goal: automate quality checks.

Tasks:

- add GitHub Actions workflow
- run Composer install
- run PHPUnit
- run PHPStan
- run PHPCS
- generate SBOM

Acceptance criteria:

- pull requests run CI
- SBOM uploaded as artefact

### Phase 8 — Docker Modernisation

Goal: make local/dev runtime reproducible.

Tasks:

- add Dockerfile
- add docker-compose.yml
- add MySQL service
- add app/web split if useful
- document local run commands

Acceptance criteria:

- `docker compose up` starts app and DB
- tests can run locally

### Phase 9 — Search Improvements

Goal: improve search quality.

Tasks:

- add MySQL full-text indexes
- introduce ranking score
- preserve click boost
- add integration tests for search relevance

Acceptance criteria:

- search results are more relevant
- click count still influences ranking

---

## 18. AI Agent Instructions

When using Codex, Copilot, or another coding agent, follow these rules:

1. Do not rewrite the whole application in one pass.
2. Keep existing behaviour working unless explicitly changing it.
3. Prefer small commits and small pull requests.
4. Add tests for every extracted class.
5. Do not introduce a full framework unless asked.
6. Do not remove existing files until replacements are working.
7. Preserve public routes initially.
8. Avoid live external HTTP calls in tests.
9. Mock network fetches.
10. Never commit real `.env` secrets.

Suggested Codex prompt:

```text
You are modernising the safesploitOrg/doogle PHP/MySQL search engine incrementally.
Use ARCHITECTURE.md as the source of truth.
Do not perform a full rewrite.
Implement the current phase only.
Preserve existing behaviour unless the phase explicitly changes it.
Add or update tests for every extracted class.
Prefer Composer, PSR-4, PHPUnit, strict types, and small cohesive classes.
Do not commit secrets.
Do not make live external HTTP calls in tests.
After changes, ensure composer test passes.
```

---

## 19. Definition of Done

A modernisation task is done when:

- code runs locally
- relevant tests exist
- all tests pass
- static analysis passes where configured
- no secrets are committed
- behaviour is documented
- README or architecture docs are updated if needed
- CI passes

---

## 20. Target End State

The desired end state is:

```text
Doogle
  |
  +-- PHP 8.x application
  +-- Composer-managed dependencies
  +-- PSR-4 namespaced app code
  +-- PHPUnit/Pest test suite
  +-- MySQL-backed search index
  +-- safe bounded crawler
  +-- service/repository architecture
  +-- Docker local runtime
  +-- GitHub Actions CI
  +-- static analysis
  +-- dependency audit
  +-- CycloneDX SBOM
  +-- documented DevSecOps workflow
```

This keeps the original spirit of Doogle while making it credible as a modern DevSecOps/platform engineering portfolio project.
