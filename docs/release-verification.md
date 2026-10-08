# Release verification and remaining gates

Verified on 8 October 2026. This is a release candidate, pending staging and repository checks.

## Local evidence

- Backend: PHP 8.5.1, 399 tests / 2426 assertions, SQLite.
- Chromium: five workflows against the real Laravel API and isolated database: administrator access, booking, availability, private credentials, and account recovery/email changes.
- Credential responses hide private paths, including legacy admin endpoints. Downloads require authorization and have private/no-store and nosniff headers.
- Recovery and verification emails reached a local SMTP sink. Password reset consumed the link and revoked two existing browser sessions. Changing the email required successful verification of the new address.
- SMTP with an empty MAIL_URL now uses host/port configuration correctly. Verification emails now render their Markdown components; a backend test exercises the real mail transport rather than faking notifications.
- MySQL 8.4.11: five simultaneous same-slot booking races, booking against schedule closure, and booking against leave creation passed with independent PHP processes. This does not cover every concurrent account/payment/attendance decision.
- A logical backup of the synthetic concurrency database was restored into a new disposable database. All 30 tables matched row contents and CREATE TABLE definitions. A synthetic private PNG was separately backed up/restored with an identical SHA-256 hash. This does not establish production recovery time, encrypted backup storage, or disaster recovery capability.

## Repeat local tests

```sh
cd backend
php vendor/bin/phpunit
cd ../frontend
npm run lint
npm test
npm run build
npm run test:e2e
```

Browser tests reserve ports 4310, 8310, 8265 and 8266. The last two are a local SMTP capture server and its health endpoint. Captures stay in ignored test directories; the sink accepts only .test recipients and does not relay mail. Install Chromium using `npx playwright install chromium --only-shell` first.

For MySQL concurrency, create a **new empty** database named `sihatech_e2e_*` and a user restricted to that database. Set APP_ENV=testing, SIHATECH_CONCURRENCY=1, DB_CONNECTION=mysql, DB_HOST/PORT/DATABASE/USERNAME/PASSWORD, a disposable APP_KEY, DB_URL empty, CACHE_STORE=array, SESSION_DRIVER=array, QUEUE_CONNECTION=sync and MAIL_MAILER=array. Run `php tests/Concurrency/run.php` from backend. It refuses cached configuration and nonempty databases. Do not point it at staging or production data. The CI MySQL service supplies a fresh database automatically.

The local MySQL distribution came from [Oracle's MySQL 8.4 downloads](https://dev.mysql.com/downloads/mysql/8.4.html). Its archive MD5 matched the published value before extraction. The local database process was stopped after testing; no Windows service was installed.

## Provider gates

```sh
php artisan integrations:check --mode=test --json
```

Run with staging configuration; use --mode=live only when checking production configuration. This read-only command reports statuses without credentials and returns nonzero for missing/dummy credentials, a Stripe mode mismatch, incorrect enabled OAuth callbacks, or mail requiring manual verification. Disabled social providers are allowed. Configuration passing does not prove credential validity, SMTP TLS behavior, webhook routing or actual delivery. SMTP relays without credentials, IAM-based SES, failover/custom transports require manual verification.

Live staging URL and provider configuration have not been supplied. Before release:

1. Complete Stripe test checkout including payment authentication, decline, cancellation, renewal, duplicate/out-of-order signed webhooks, and local access reconciliation. Confirm a webhook cannot grant access for another user or price. Keep test and live credentials separate.
2. Complete enabled Google/Facebook success, cancellation and failure flows on deployed HTTPS domains. Confirm provider callback URLs and CSRF/session cookies. Facebook remains limited to existing accounts.
3. Deliver recovery/verification/reminder email to controlled staging mailboxes, follow the links on the deployed frontend, and check revoked sessions across devices. Test queue retry behavior without duplicate reminders.

## Data and operations gates

Before migrating existing data, take encrypted database **and private storage** backups, retain the existing APP_KEY and secrets separately, and restore a copy in an isolated environment. Record restore duration, backup age, permission checks and file hashes. Ensure backups are recoverable without relying on the original host.

Use the read-only commands `subscriptions:audit-periods --json`, `appointments:audit-attendance`, and `documents:privatize` first. Compare subscription anomalies with Stripe. Historical appointment ownership must be reviewed against trustworthy booking records: the old user-ID/profile-ID mistake can produce valid foreign keys pointing at the wrong patient, so a SQL integrity check cannot safely repair it. Do not bulk rewrite ownership or paid access based on guesses.

Rehearse migrations on a copy, resolve duplicate catalogue/review/social records, then privatize credentials during a maintenance window using the documented --apply procedure. Verify old public/CDN URLs no longer serve files. Provision queue workers, scheduler heartbeats and alerts, and test readiness failure/recovery. See [production fixes](production-fixes.md) and [operations verification](operations-verification.md).

## GitHub gates

The verification workflow includes backend PHP 8.3/8.5, frontend lint/tests/build/audit, Chromium, and MySQL concurrency jobs. Main protection was applied and read back on 8 October 2026: all five contexts are bound to the GitHub Actions app (15368), pull requests must be up to date, administrators must obey checks, review conversations must be resolved, and force pushes/deletion are disabled. Pull requests are required; no external approving reviewer is required for this single-owner repository. The exact configuration is saved in [main-branch-protection.json](main-branch-protection.json).

The accumulated changes are published in [draft PR #1](https://github.com/theRealest555/SIHATECH/pull/1). The first remote run passed frontend, Chromium and MySQL but exposed a test assuming a future Casablanca UTC offset. Windows/Linux timezone databases differed. The regression now uses Asia/Kolkata's stable +05:30 offset, preserving the check that configured non-UTC times are serialized correctly. [All five jobs passed for the corrected commit](https://github.com/theRealest555/SIHATECH/actions/runs/37786222562). Verify the latest commit's checks before merging; subsequent changes also trigger the required jobs.

Do not merge or deploy until GitHub checks and the staging/provider/data gates above pass. Deployment and rollback must preserve data and private files; use an application rollback compatible with the migrated schema rather than blindly dropping new columns.
