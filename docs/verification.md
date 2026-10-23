# Implementation verification

Recorded on **2026-10-24**. These results describe checks actually run in the development workspace, not a claim that GitHub Actions has already executed.

## Local environment

- PHP 8.5.12 CLI, with in-memory SQLite support.
- Composer 2.10.3.
- PHPUnit 12.5.40, PHPStan 2.3.1 and PHP-CS-Fixer 3.95.27.

## Executed checks

| Check | Result |
| --- | --- |
| `composer validate --strict` | Passed. |
| PHPUnit with Symfony 7.4.21 / DBAL 3.10.6 | 99 tests, 345 assertions; passed on PHP 8.5.12. |
| PHPUnit with Symfony 8.0.15 / DBAL 4.5.0 | 99 tests, 345 assertions; passed on PHP 8.5.12. |
| PHPUnit with Symfony 8.1.9 / DBAL 4.5.0 | 99 tests, 345 assertions; passed on PHP 8.5.12. |
| PHPStan maximum level, source and tests, target PHP 8.4 | Passed, no errors. |
| PHP-CS-Fixer dry run | Passed, no outstanding changes. |
| Git diff whitespace check | Passed. |
| CI workflow YAML parsing | Passed. |
| JSON Schema draft 2020-12 validation | Both schemas, real endpoint payloads and README response examples validated successfully. |
| Distribution ZIP | Created and inspected; development/vendor/test directories excluded. |
| Separate production installation from that ZIP | Both endpoints boot and respond with only production dependencies. |
| Optional components absent | Doctrine DBAL, Doctrine Migrations, Messenger and Sulu absent; optional checks omitted without failure. |
| Authoritative Composer autoloading | Both canonical and Flex-discovered bundle entry points boot successfully. |
| Symfony Flex 2.11 discovery | Its actual `SymfonyBundle::getClassNames()` helper discovers the compatibility alias in the installed distribution. |
| Restricted hosting simulation | Disabled `disk_free_space`, `disk_total_space` and `opcache_get_status`; storage reports `unknown`, OPcache `unsupported`, authenticated endpoints remain usable. |

The test suite covers authorization before check construction/data gathering, missing/null/invalid tokens, token rotation, HTTPS/IP restrictions, response structure, route configuration, per-check failures, constructor failures in a compiled Symfony container, cache isolation, sanitized Composer information and read-only migration metadata inspection. Database and migrations tests use SQLite in memory and do not create a migrations metadata table.

## Scope and limitations

PHP 8.4 is not installed locally, and the available Docker socket is inaccessible to this workspace. PHP 8.4 execution is configured in CI; local static analysis explicitly targets PHP 8.4. No successful local PHP 8.4 or remote CI run is claimed.

Sandbox restrictions prevent worker coordination through local TCP sockets. PHPStan was run with `--debug` and PHP-CS-Fixer with `--sequential`. PHP-CS-Fixer emitted its PHP 8.5-versus-minimum-8.4 advisory; the configured rules do not introduce PHP 8.5 syntax.

Symfony 8.0 is unmaintained and Composer blocks its affected dependencies by default. Only its isolated compatibility fixture and the corresponding disposable CI matrix job use `--no-security-blocking`. The normal package configuration retains Composer's security protection. Distribution installation smoke tests reused the downloaded Composer cache; they are not a fresh independent vulnerability audit.

No live MySQL/PostgreSQL server, Redis server, message broker, Sulu application or hosting account was used. Real backend-specific networking, proxy configuration, transport auto-setup settings and timeouts still require validation in the consuming deployment. The bundle intentionally does not implement remote vulnerability/update checks or unreliable interruption of blocking PHP calls.
