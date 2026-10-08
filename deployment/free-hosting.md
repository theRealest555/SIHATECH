# Free testing deployment

Prepared for a small synthetic-data test environment on alwaysdata Free. This is not a production deployment. No account or remote resource has been created.

## Hosting choice and account step

The [Free plan](https://www.alwaysdata.com/en/offers/) currently advertises 1 GB disk space, 256 MB RAM and one-quarter CPU for personal use. alwaysdata supports [PHP](https://help.alwaysdata.com/en/docs/web-hosting/languages/php/), [MariaDB/MySQL](https://help.alwaysdata.com/en/docs/web-hosting/databases/mariadb/) and [SSH](https://help.alwaysdata.com/en/docs/web-hosting/remote-access/ssh/). This fits the current application's small test deployment without changing its storage/database architecture. Check the current Free plan restrictions when creating the account.

Complete [registration](https://www.alwaysdata.com/en/register/) yourself, select Free and provide the issued account hostname. Registration currently requires card validation and says no fees are charged. Do not select a paid trial/upgrade. If card verification is unsuitable, stop before entering billing details; another host would need to be evaluated.

The supplied files are already prepared for upload. They contain no active .env, databases, uploaded documents, mail captures or cached application configuration. Production Composer dependencies are installed on the server, not copied from the development vendor directory.

## Prepared bundle

From the repository root with frontend dependencies already installed:

```sh
node deployment/prepare-free-hosting.mjs
```

The output is a new sibling .local-tools/free-hosting-TIMESTAMP directory. It combines the built React UI and tracked Laravel source. It never uploads anything or changes the working backend public/.htaccess. Local Vite development remains separate. The generated Apache rules serve the UI at /, assets at /frontend/, and Laravel at /api/*, /sanctum/*, /health and /up. API requests use the current origin, so a final hostname is not needed to compile this build.

## Account configuration

1. Choose PHP 8.3 or newer for the site and CLI. Check the selected PHP binary and extensions against backend/composer.lock. Create a separate MariaDB database and database user in the host panel; record the provided host/name privately.
2. Upload the bundle to a private account directory such as ~/sihatech-test. Set the PHP site's document root to ~/sihatech-test/backend/public, never ~/sihatech-test or backend. Use the account's assigned alwaysdata.net address. Confirm TLS and enable the host's [HTTP-to-HTTPS redirect](https://help.alwaysdata.com/en/docs/web-hosting/sites/ssl-tls/redirect-http-to-https/).
3. Create backend/.env from the included backend.env.example. Replace the placeholder origin with the assigned HTTPS origin and SANCTUM_STATEFUL_DOMAINS with its hostname. Set DB_CONNECTION=mysql and the issued MariaDB host/database/user/password. Keep SESSION_DOMAIN=null for host-only cookies. Supply provider credentials here, never in the React build or Git.
4. In backend, run `composer install --no-dev --prefer-dist --optimize-autoloader`. Generate APP_KEY once for this new empty test environment; preserve it on subsequent deployments. Ensure storage and bootstrap/cache are writable by this account without making them world-writable. Set DOCUMENTS_ROOT to persistent private storage outside the web document root, or keep its default storage/app/private location.
5. Verify the database is this new empty test database before `php artisan migrate --force`. No sample accounts or real patient data are included. Create controlled test users through the existing registration/approved administrator provisioning procedure; do not enable development seeders on the public deployment.
6. Provision a scheduled task running `php artisan schedule:run` each minute from backend. Provision a supervised worker using the existing operations guide, or for a small test environment schedule `php artisan queue:work database --queue=default --stop-when-empty --tries=3 --backoff=10 --timeout=60` each minute, with a task overlap lock. This bounded worker has minute-level latency and is not an always-on production worker. With OPS_REQUIRE_HEARTBEATS=true, /health must fail when these processes stop; confirm recovery after they run again. Keep appointment reminders disabled initially.
7. Run configuration/route/view cache commands from deployment/README.md after the environment is complete. Check /login and /admin/dashboard reloads, missing /frontend/assets files (404), /api/public/auth/providers (JSON), /health and authenticated API access. Apache routing has not yet been exercised on a remote account.

## Provider setup after the hostname exists

- Stripe: configure the existing account's test keys and a signed event destination at https://HOST/api/webhooks/stripe. Use the event list in deployment/README.md and that destination's signing secret.
- Google/Meta: register exact callbacks https://HOST/api/auth/social/google/callback and https://HOST/api/auth/social/facebook/callback in the existing developer accounts. Keep each provider disabled until configured and tested.
- Email: outbound provider credentials and a verified sender are still required. A free test sender is often restricted to the account owner's address; verify those restrictions in the provider dashboard before testing. An alwaysdata.net website address does not grant ownership of DNS for arbitrary sending-domain verification. The app already supports SMTP; no fake credentials are generated.

Complete ../docs/release-verification.md after deployment. Free hosting quotas, the host's MariaDB engine and scheduled worker behavior must be verified on the real account. Use synthetic test records only. The user selected free hosting for testing; paid services are outside that authorization. Account registration and access are still needed before any public deployment can occur.
