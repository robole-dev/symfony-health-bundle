# Symfony Health Bundle

`robole/symfony-health-bundle` provides authenticated JSON health reports and a separate
Composer inventory for Symfony applications. It is designed for external website monitoring
across dedicated servers and shared hosting.

## Features

- Independent, automatically registered health checks with isolated failures.
- Database, storage, cache, Messenger, OPcache and pending migration checks.
- Separate application, PHP/Symfony and Composer information providers.
- Bearer tokens with rotation, optional IP/CIDR restrictions and HTTPS enforcement.
- A small summary endpoint and a separately protected package inventory endpoint.
- Versioned JSON schemas, conservative defaults and no shell commands or external API calls.

Dependency updates and vulnerability analysis belong in the consuming monitoring system. The bundle does not run Composer, Git, migrations or message consumers during requests.

## Requirements and compatibility

- PHP **8.4 or later within PHP 8.x**.
- Symfony **7.4 or 8.x**, subject to each component's PHP requirements.
- Composer 2.2+ and FrameworkBundle with routing enabled.
- Optional: Doctrine DBAL **3.10 or 4.x**, Doctrine Migrations **3.9.x**, Symfony Messenger **7.4 or 8.x**.

CI covers PHP 8.4/8.5 and Symfony 7.4/8.0/8.1, including DBAL 3/4. The code uses APIs available
in Symfony 7.4. Future Symfony minors are allowed by Composer and should be added to CI when
released. Symfony 8.0 is retained for compatibility testing; use a maintained Symfony
branch for production. See the [official Symfony release information](https://symfony.com/releases).

## Installation and Symfony integration

Once the package is available through Packagist or your Composer repository:

```bash
composer require robole/symfony-health-bundle
```

For an unpublished checkout, add a Composer `path` or VCS repository to the consuming
application and require `robole/symfony-health-bundle:@dev`. This repository is a bundle,
not a Symfony application.

Symfony Flex can register the bundle automatically.

Without Flex, add the canonical class to `config/bundles.php`:

```php
return [
    // Other bundles...
    Robole\SymfonyHealthBundle\RoboleHealthBundle::class => ['all' => true],
];
```

Import the routes in `config/routes/robole_health.yaml`:

```yaml
robole_health:
    resource: '@RoboleHealthBundle/Resources/config/routes.php'
```

Alternatively, import `resource: .` with `type: robole_health`. Import the routes once. The loader
applies the configured prefix and removes disabled endpoints from the route collection.

Create `config/packages/robole_health.yaml`:

```yaml
robole_health:
    api:
        tokens: ['%env(HEALTH_API_TOKEN)%']
    application:
        name: 'Example Application'
```

Provide a randomly generated token through deployment secrets, Symfony secrets, or a protected
environment file. For example, generate 32 random bytes
with `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`. Do not commit the resulting value.

### Existing SecurityBundle firewalls

The bundle authenticates inside its own controllers before resolving checks or collecting
information. An application firewall can still intercept the request first, for example
with an HTML login redirect. In that case, place a dedicated firewall **before** the
application's catch-all firewall:

```yaml
# config/packages/security.yaml
security:
    firewalls:
        robole_health:
            pattern: ^/api/health(?:/|$)
            security: false
        # main: ... existing application firewall ...
```

This delegates authentication for those paths to the bundle's Bearer-token guard. Adjust the
pattern when changing the API prefix, and retain the bundle's token configuration.
No SecurityBundle dependency is required by the bundle itself.

## Authentication and transport security

Both endpoints require:

```http
Authorization: Bearer YOUR_TOKEN
```

```bash
curl --header "Authorization: Bearer ${HEALTH_API_TOKEN}" \
    https://example.com/api/health
```

Tokens are never accepted from query strings, cookies or request bodies. Missing/invalid tokens,
an empty token list and empty resolved token values return `401` with `{"error":"unauthorized"}`
and a Bearer challenge. A referenced but missing Symfony environment variable is a deployment
configuration error; Symfony may reject service creation, and access still fails closed.
The guard compares fixed-length SHA-256 token digests with `hash_equals()` and checks every
rotation slot. Use high-entropy tokens; the digest comparison is not a password hashing scheme.

For rotation, configure both old and new tokens, update clients, then remove the old token and
rebuild the application's container when required:

```yaml
robole_health:
    api:
        tokens:
            - '%env(HEALTH_API_TOKEN)%'
            - '%env(HEALTH_API_TOKEN_NEXT)%'
        allowed_ips: ['192.0.2.10', '198.51.100.0/24', '2001:db8::/32']
```

HTTPS is required by default. A valid token over HTTP, or from a disallowed client IP, receives
a generic `403`. Configure Symfony's **trusted proxies and trusted headers** correctly when
TLS terminates at a reverse proxy. The bundle uses `Request::isSecure()` and
`Request::getClientIp()`; arbitrary forwarded headers are not trusted automatically.
Restrict the origin so untrusted clients cannot impersonate a trusted proxy. Never send
tokens over plain HTTP. `require_https: false` is available for controlled local development.

Responses include `Cache-Control: private, no-store, max-age=0`, `Pragma: no-cache` and
`X-Content-Type-Options: nosniff`. Configure reverse proxies/CDNs to bypass caching for
these paths. The bundle does not log tokens, requests, exceptions or credentials; configure
the host application's profiler, access logs, APM and proxy logs to redact Authorization
headers. Do not expose a debug profiler publicly.

## Complete configuration reference

The following lists **every option with its default**. `null` metadata remains absent in meaning
and is serialized as JSON `null`. Environment placeholders are supported where Symfony normally
permits them.

```yaml
robole_health:
    enabled: true

    api:
        enabled: true
        health_enabled: true
        dependencies_enabled: true
        prefix: /api/health
        tokens: []                        # Empty means deny all requests.
        allowed_ips: []                   # Empty means no extra IP restriction.
        require_https: true
        critical_http_status: 200         # Allowed: 200 or 503.

    application:
        name: Application
        version: null
        git_commit: null
        git_branch: null
        deployed_at: null
        include_sulu_version: true

    system:
        include_extensions: false

    checks:
        database: true
        storage: true
        cache: false
        messenger: true
        opcache: true
        doctrine_migrations: true

    database:
        connection: doctrine.dbal.default_connection  # Service ID.

    storage:
        include_space: false

    cache:
        pool: cache.app                   # PSR-6 pool service ID; only probed when enabled.

    messenger:
        transports: {}                    # Explicit aliases -> {service, read_only: true}.
        failure_transports: []            # Aliases from transports, not DSNs.
        failed_warning_threshold: 1

    migrations:
        dependency_factory: doctrine.migrations.dependency_factory
        pending_status: warning           # Allowed: warning or critical.

    performance:
        warning_thresholds_ms: {}          # Check names -> positive integer milliseconds.

    dependencies:
        enabled: true                     # false also removes the inventory route.
        project_dir: '%kernel.project_dir%'
        include_dev: false
        max_file_size_bytes: 10485760      # Per file; 1 KiB through 50 MiB.
        cache_pool: cache.system          # Optional PSR-6 service; null disables caching.
        cache_ttl: 60                     # Seconds; 0 disables caching; maximum 86400.
```

`enabled: false` removes both routes and the reporting/check services. `api.enabled: false`
removes both routes while keeping services available for application use. The two endpoint
switches work independently. `checks.*: false` removes the corresponding bundled service;
application-defined checks remain registered while the bundle is enabled. Optional package
absence omits the corresponding Doctrine/Messenger check. An installed package with a missing
connection/factory returns `unsupported`.

The prefix must be an absolute path made of letters, digits, `_`, `-` and path separators,
without placeholders, query parameters or a trailing slash. IP ranges, positive thresholds,
size limits, migration status and Messenger aliases are validated. Invalid/unknown
configuration options fail container compilation.

### Deployment metadata

```yaml
robole_health:
    application:
        name: '%env(APP_NAME)%'
        version: '%env(APP_VERSION)%'
        git_commit: '%env(APP_GIT_COMMIT)%'
        git_branch: '%env(APP_GIT_BRANCH)%'
        deployed_at: '%env(APP_DEPLOYED_AT)%'
```

Set these values explicitly during deployment. Optional variables are not read implicitly; omit
options you do not provide. No `.git` directory is needed. Commits must be 7–64 hexadecimal
characters. Branches accept a restricted, length-limited Git-like identifier. Deployment
timestamps must be RFC 3339 timestamps with seconds and an explicit UTC marker/offset, for
example `2026-10-22T20:30:00+02:00`; output is normalized to UTC. Invalid optional metadata
becomes `null`. Names/versions are bounded UTF-8 text without control characters. These are
intentionally published metadata fields: never configure secrets or server paths as their values.

The application environment and Symfony version come from the kernel. Sulu's version is obtained
only from Composer's installed-package metadata for `sulu/sulu`; otherwise it is `null`. The
system provider independently supplies PHP version/SAPI, memory limit, Symfony
version/environment/debug, server timezone and UTC time. Extension names are opt-in. It does
not dump environment variables, `phpinfo()`, server configuration or paths.

## API and JSON contract

Both endpoints return `application/json`. JSON Schema draft 2020-12 definitions are provided
in [docs/schema](docs/schema). Schema version **1.0** uses lowercase status strings, UTC timestamps
with second precision, millisecond durations and byte-valued storage metrics. Optional
unavailable values are `null`; empty mappings are JSON objects. Clients should tolerate
additional fields in future schema revisions and use `schema_version` for incompatible changes.

### `GET /api/health`

```json
{
  "schema_version": "1.0",
  "timestamp": "2026-10-23T12:00:00Z",
  "status": "healthy",
  "application": {
    "name": "Example Application",
    "environment": "prod",
    "version": "1.2.0",
    "symfony_version": "7.4.21",
    "sulu_version": null,
    "git_commit": "abc1234",
    "git_branch": "main",
    "deployed_at": "2026-10-22T18:30:00Z"
  },
  "system": {
    "php_version": "8.4.27",
    "php_sapi": "fpm-fcgi",
    "memory_limit": "256M",
    "symfony_version": "7.4.21",
    "environment": "prod",
    "debug": false,
    "timezone": "Europe/Berlin",
    "utc_time": "2026-10-23T12:00:00Z"
  },
  "dependencies": {
    "status": "healthy",
    "lock_hash": "sha256:0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef",
    "production_packages": 120,
    "development_packages": 35
  },
  "checks": {
    "database": {
      "status": "healthy",
      "duration_ms": 1.234,
      "code": "database_reachable"
    },
    "opcache": {
      "status": "unsupported",
      "duration_ms": 0.012,
      "code": "opcache_unavailable_for_sapi"
    }
  }
}
```

`status` aggregates the enabled check results only. Application/system/dependency metadata does not silently change check health; inspect `dependencies.status` separately. Disabled dependency reporting produces `dependencies: null`.

| Status | Meaning |
| --- | --- |
| `healthy` | The checks performed succeeded. |
| `warning` | An observed condition needs attention, such as pending migrations. |
| `critical` | A probe failed or a check threw an exception. |
| `unknown` | A configured measurement could not be obtained reliably. |
| `unsupported` | The capability or integration is unavailable. |

Aggregation priority is **critical > warning > unknown > healthy > unsupported**. Unsupported optional capabilities do not downgrade an otherwise healthy report. Only unsupported results produce `unsupported`; no registered checks produce `unknown`. Storage and Messenger use the same rule for their nested components. A healthy result establishes only the capability it probes, not overall application correctness.

### HTTP status strategy

By default every authenticated, successfully generated report returns **HTTP 200**, including warning/critical reports. Monitoring clients can always parse the JSON body without an HTTP library discarding it as an error. Clients **must inspect the JSON status** and distinguish network/authentication failure from application health. This endpoint is not an unauthenticated load-balancer liveness probe.

Set `api.critical_http_status: 503` if your client treats critical health as an HTTP failure. The full JSON body is still returned. Warning, unknown and unsupported reports remain HTTP 200. The inventory endpoint returns HTTP 200 with its own `status`, including `unknown` when files cannot be read. Authentication failures are 401; authenticated HTTPS/IP restriction failures are 403; disabled routes are 404; unsupported HTTP methods are 405. Unexpected host/kernel errors can still produce a server error.

### `GET /api/health/dependencies`

```json
{
  "schema_version": "1.0",
  "timestamp": "2026-10-23T12:00:00Z",
  "status": "healthy",
  "lock_hash": "sha256:0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef",
  "production_packages": 1,
  "development_packages": 0,
  "packages": [
    {
      "name": "vendor/library",
      "version": "1.2.3",
      "type": "library",
      "dependency_type": "production"
    }
  ],
  "constraints": {
    "require": {"php": "^8.4", "vendor/library": "^1.2"},
    "require-dev": {},
    "conflict": {},
    "config": {"platform": {"php": "8.4.0", "ext-redis": false}}
  }
}
```

The provider reads only local `composer.lock` and `composer.json`, with bounded sizes and JSON depth, and copies an allowlist of fields. It validates names, types, versions and constraints using restricted character sets and `composer/semver`. Source/dist/repository URLs, scripts, authors, extra metadata, authentication configuration and `auth.json` are never transmitted. Invalid or missing files produce `unknown`, a null hash/counts, and an empty inventory rather than echoing invalid data. No dependency resolver runs in a request.

**Inventory and counts describe the lock file.** They cannot prove that every locked package is present in `vendor/`, or that a dev package was installed in production. `packages` is sorted by name and is a full normalized inventory of the selected scope; `include_dev: true` includes development packages and root `require-dev` constraints. By default both are omitted, while both lock-file package counts remain available. The SHA-256 hash covers the exact lock bytes used for that snapshot. Constraints come from `composer.json`; changing constraints without changing the lock does not change the lock hash.

## Health checks

### Database

Uses the configured DBAL connection service and the platform's dummy select (`getDummySelectSQL()`), supporting platform-specific syntax without reading application tables. Reports query/connection duration in milliseconds. An unavailable connection returns `unsupported`; a failed query or unexpected result is `critical`. No DSN, SQL error, database name or driver exception is returned. Existing DBAL middleware, logging and driver timeouts remain the host application's responsibility.

### Storage

Inspects only `kernel.cache_dir` and `kernel.logs_dir`: existence and writability, without creating directories or test files. Missing or unwritable directories are `critical`. Optional disk free/total byte measurements are `unknown` when disabled by the host or unavailable. Measurements reflect the filesystem and may **not** reflect account quotas, inode availability or guaranteed successful future writes. Paths are never included. Warm the application cache and provision the log directory before monitoring.

### Cache

Opt-in PSR-6 pool probe: writes a random 128-bit namespaced key/value with a 30-second TTL, reads it, deletes it, and verifies absence. It never clears the pool. A pre-existing randomly chosen key is left untouched and reported as `unknown`; normal operations touch only `robole_health_probe_*`. Exceptions are sanitized; cleanup is retried after failure. A backend that refuses deletion may retain only that probe until its TTL expires. Do not use this reserved prefix for application keys.

### Messenger

Installed Messenger enables the check, but **no transports are probed implicitly**. Configure explicit service aliases only after verifying that their count operation is read-only:

```yaml
robole_health:
    messenger:
        transports:
            async:
                service: messenger.transport.async
                read_only: true
            failed:
                service: messenger.transport.failed
                read_only: true
        failure_transports: [failed]
        failed_warning_threshold: 1
```

Only Symfony's [MessageCountAwareInterface](https://symfony.com/doc/7.4/messenger.html#checking-the-number-of-queued-messages-per-transport) is used. No message is consumed, sent, acknowledged, rejected or inspected. Unsupported/missing services return `unsupported` per alias; no configured transports produce `unsupported`. Count errors affect only that component. A failure transport at/above the threshold is `warning`; a normal queue count alone does not imply unhealthy workers. Counts can be approximate or backend-dependent.

Some transports auto-create queues/tables when requesting statistics. The interface alone does **not** promise read-only behavior. Disable any such automatic setup in the application (for example the transport's `auto_setup` option where supported), provision queues during deployment, and review the concrete adapter before declaring `read_only: true`. If that guarantee is unavailable, leave the transport unconfigured. The bundle does not pretend it can infer safe behavior from an arbitrary adapter.

### OPcache

Calls `opcache_get_status(false)` to avoid the script/path list. Returns only selected numeric counters, memory metrics and booleans. Full caches are `warning`. Missing/restricted functions, a disabled extension, or lack of OPcache in the current SAPI return `unsupported`. CLI status is not evidence about PHP-FPM status. Shared hosting can expose a server-wide cache rather than an application-specific one; disable this check if those metrics should not be collected.

### Doctrine Migrations

Uses the installed Migrations dependency factory's public `getMigrationStatusCalculator()->getNewMigrations()` API. It reads migration metadata and the configured migration repository only, returning a pending count. No metadata table initialization, execution plan, migration execution or schema update is requested. No pending migrations is `healthy`; pending migrations use `migrations.pending_status`; inaccessible metadata is `unknown`. Migrations may discover classes in their **explicitly configured migration directories**; large migration histories can make this check more expensive. Disable it where that cost is unsuitable or custom metadata providers cannot guarantee read-only behavior.

## Custom checks

Checks are independent services implementing `HealthCheckInterface`. With Symfony's usual `autoconfigure: true`, they receive `robole_health.check` automatically. Their constructors must not perform I/O; defer work until `check()` and apply backend-specific timeouts.

This complete example reports a deployment maintenance flag without contacting any external service:

```php
<?php

declare(strict_types=1);

namespace App\Health;

use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;
use Robole\SymfonyHealthBundle\Health\HealthCheckInterface;
use Robole\SymfonyHealthBundle\Health\Result\HealthCheckResult;

final readonly class MaintenanceModeHealthCheck implements HealthCheckInterface
{
    public function __construct(private bool $maintenanceMode = false)
    {
    }

    public function getName(): string
    {
        return 'maintenance_mode';
    }

    public function check(): HealthCheckResult
    {
        return new HealthCheckResult(
            $this->maintenanceMode ? HealthStatus::Warning : HealthStatus::Healthy,
            code: $this->maintenanceMode ? 'maintenance_enabled' : 'maintenance_disabled',
            metrics: ['maintenance_mode' => $this->maintenanceMode],
        );
    }
}
```

```yaml
# config/services.yaml; set APP_MAINTENANCE_MODE=0 or 1 during deployment.
services:
    App\Health\MaintenanceModeHealthCheck:
        autoconfigure: true
        arguments:
            $maintenanceMode: '%env(bool:APP_MAINTENANCE_MODE)%'
```

Without autoconfiguration, use `tags: [{name: robole_health.check, priority: 0}]`. Priority controls execution order; do not add both registration methods. Names, component keys, metric keys and error codes must match `[a-z][a-z0-9_]{0,63}`. Names must be unique, including bundled checks. Results accept only numeric/boolean/null metrics, optional duration/code, and nested `HealthCheckResult` components. Free-text exception fields, arbitrary objects and string-valued metrics are deliberately unsupported. Custom developers must also keep secrets out of identifiers and configured metadata.

The runner measures total execution time unless the check reports a more specific duration. A thrown `Throwable` becomes `critical` / `check_failed`, with no exception details. Failed service initialization, invalid names or duplicates get a synthetic `registration_failure_N` result with `check_initialization_failed`, while other services still run. `HealthCheckRegistry::all()` is also available for strict name validation outside the HTTP runner. Checks have no dependency on the controller.

## Sulu integration

Install and register the bundle in the Sulu project's Symfony kernel using the same steps. Import routes for the relevant website/admin kernel as appropriate for that project's routing structure. Keep monitoring outside Sulu's interactive login firewall using a narrowly matched firewall as shown above. Verify the final routes with `bin/console debug:router` in the environment/kernel being monitored.

Only Sulu projects whose PHP/Symfony constraints satisfy this package can install it. There is no claim of compatibility with every historical Sulu version. Sulu is not loaded by the bundle; the optional reported version comes from Composer metadata. Projects without Sulu behave identically apart from `sulu_version: null`.

## Shared hosting and performance

- No shell/process extensions, `.git`, Redis, message broker or external monitoring API is required.
- No application-wide directory scans occur. Only two directories and two Composer files are inspected; the optional migrations library may discover its configured migration classes.
- Checks run synchronously and sequentially with isolated exceptions. They do not modify application records. Cache probes and the optional information cache write only their own keys.
- `performance.warning_thresholds_ms: {database: 500}` reports slow otherwise healthy checks as `warning` **after completion**. It is a duration budget, **not a hard timeout**.
- Portable PHP cannot safely interrupt an arbitrary blocking DB/cache/transport call inside a web request. Configure connection/read/query timeouts in the concrete client/driver, and a monitoring-client request timeout. A blocking native call or fatal process failure cannot be isolated by catching exceptions. No unreliable global `pcntl_alarm()`, fork or `set_time_limit()` timeout is advertised.
- The default optional dependency-information cache uses `cache.system` for 60 seconds. Missing/broken cache services fall back to direct bounded reads; `cache_pool: null` or `cache_ttl: 0` disables it. Cache entries are scoped by project directory and inventory settings. Clear the pool at deployment when immediate inventory freshness is needed; otherwise the lock snapshot can remain stale until TTL expiry. Do not share that pool with untrusted applications.
- Start with a sensible polling interval, for example once per minute, then measure your host. Put rate limits at the reverse proxy if needed. Authenticated clients are trusted to request technical inventories; authentication alone does not provide polling limits.
- On hosts that disable storage functions or OPcache statistics, unavailable information stays `unknown`/`unsupported`. Disk capacity reporting is off by default.

The bundle exposes deliberate technical information only to authenticated clients. It has no public status page, CORS configuration, token query parameter or vulnerability feed. Disable the inventory or extension list if that disclosure is unnecessary for your monitoring system.

## Tests and development

```bash
composer install
composer validate --strict
composer test
composer analyse
composer cs:check
```

Use `composer cs:fix` to apply style changes. PHPStan runs at its maximum level over source and tests. Tests use in-memory SQLite, PSR-6 memory caches, stubs and a small fixture kernel; no production database, Redis, broker or external service is required. CI installs a PHP/Symfony/DBAL matrix and separately installs a packaged bundle **without dev dependencies**, testing both endpoints, missing optional components, authoritative autoloading and disabled shared-hosting functions.

In sandboxed environments that prohibit local TCP sockets, use `vendor/bin/phpstan analyse --debug` and `vendor/bin/php-cs-fixer fix --sequential --dry-run --diff` to avoid worker-process socket coordination. A PHP 8.5 style run may warn that PHP 8.4 is the package minimum; the rules do not enable PHP 8.5 migration features.

See [CONTRIBUTING.md](CONTRIBUTING.md), [CHANGELOG.md](CHANGELOG.md), and [docs/verification.md](docs/verification.md) for development guidance and the recorded verification scope. The [MIT license](LICENSE) retains the repository's existing copyright notice.
