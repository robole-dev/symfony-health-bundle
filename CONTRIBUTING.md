# Contributing

Use PHP 8.4+ and Composer 2.2+. Install dependencies with `composer install`. This is a reusable Symfony bundle; keep framework applications confined to test fixtures.

Before proposing a change, run:

```bash
composer validate --strict
composer test
composer analyse
composer cs:check
```

Run style tooling on PHP 8.4 when possible. Restricted environments may require PHPStan's `--debug` and PHP-CS-Fixer's `--sequential` options. The library intentionally does not commit `composer.lock`; CI resolves compatible dependencies for each matrix entry.

Use strict types, constructor injection, explicit types and small services. Add behavior-focused tests for new capabilities and regressions, especially authorization boundaries, optional dependencies, failures and data disclosure. Tests must not require external services. Run checks against both the lowest supported Symfony branch and a current 8.x branch when changing framework integration. DBAL integrations must support DBAL 3.10 and 4.x.

Do not introduce commands, remote API calls, application data mutations or broad filesystem discovery into request handling. Validate any new data crossing the JSON boundary. Never return raw exception messages, connection strings, paths or arbitrary Composer metadata. No check may consume or modify messages or execute migrations.

Document configuration defaults, status/HTTP behavior and performance limits in the README. Keep versioned JSON schemas aligned with payloads and describe user-visible changes in `CHANGELOG.md`. Breaking changes to the API contract require a new schema version.

For suspected security issues, use a private reporting channel provided by the repository host if available. Do not include real credentials, private customer data or exploit details in a public issue.
