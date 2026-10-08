# Staging setup

These templates prepare the existing React/Laravel application for staging. They do not create hosting, domains, provider accounts or credentials. No service has been purchased or deployed.

For the requested free test environment, see [free hosting setup](free-hosting.md) and run `node deployment/prepare-free-hosting.mjs` to prepare a combined frontend/backend upload bundle.

## Hosting requirements

Use PHP 8.3 or newer with the extensions required by backend/composer.lock, Composer, a MySQL database, persistent private storage, a shared cache, an asynchronous queue worker and a scheduler. Serve Laravel from backend/public only; never expose the repository root. Serve the built frontend/dist and route React URLs to index.html. Route /api/*, /sanctum/*, /health and /up to Laravel. The provided templates assume both are served on one HTTPS origin. Do not apply them unchanged to unrelated frontend/backend domains.

[Laravel deployment documentation](https://laravel.com/framework/docs/12.x/deployment) covers web-server configuration, writable storage/cache directories and configuration caching. [Laravel Cloud](https://laravel.com/cloud/deploy-a-laravel-app) supports Laravel hosting and supplies a platform domain. Hosting costs and an account are required; this repository has not been configured for a specific host. Confirm persistent local document storage support before selecting a host, or implement and test a supported shared storage adapter.

## Environment and build

Enter values from backend.env.example into the backend host's environment settings. Replace every staging.example.invalid origin with the real HTTPS origin. Supply database credentials there. For a new empty environment generate APP_KEY once with php artisan key:generate --show and store it securely; preserve the existing key when moving encrypted data. Never commit active environment files or secrets.

Install the backend with `composer install --no-dev --prefer-dist --optimize-autoloader`. Install/build the frontend with `npm ci` and `npm run build`, using frontend.env.example as the frontend build environment. VITE_API_BASE_URL is public; it must not contain credentials.

Rehearse migrations on an isolated database copy before deploying existing data. A new staging database may be migrated with `php artisan migrate --force` after its target is verified. Configure writable, persistent storage and bootstrap/cache, then run `php artisan config:cache`, `php artisan route:cache` and `php artisan view:cache` on the host. Provision queue/scheduler processes following ../docs/operations-verification.md. Health requires their heartbeats with this template.

## Stripe

Use the existing Stripe account's test environment. Obtain its publishable and secret keys and set STRIPE_KEY / STRIPE_SECRET privately on the backend. Create the HTTPS event destination at `/api/webhooks/stripe` and set that destination's STRIPE_WEBHOOK_SECRET. Stripe issues these credentials; generating random strings cannot replace them. See [API keys](https://docs.stripe.com/keys) and [webhooks](https://docs.stripe.com/webhooks).

The application handles payment_intent.succeeded, payment_intent.payment_failed, customer.subscription.created, customer.subscription.updated, customer.subscription.deleted, invoice.payment_succeeded and invoice.payment_failed. Select these events for the destination, then validate test checkout, paid invoices, duplicates, failed payments and cancellations. Configure real test prices through the existing administrator plan workflow. A configuration check alone does not establish payment correctness.

## Google and Facebook

In the existing developer accounts, create/configure the OAuth application for this staging origin. Register the exact callback URLs in backend.env.example after replacing the placeholder origin. Store IDs/secrets privately on the backend; set each enabled flag true only after its setup is complete. [Google's server OAuth documentation](https://developers.google.com/identity/protocols/oauth2/web-server) describes exact redirect URI matching. Facebook remains restricted to previously linked accounts by the existing application behavior; do not promise new Facebook account registration.

## Email

Create an outbound email-provider account and verify a sending identity/domain. Add its SMTP host, port, security mode, username/password and verified MAIL_FROM_ADDRESS to the backend environment. No new SDK is needed for SMTP. For example, [Resend supports SMTP](https://resend.com/features/smtp-service); its current settings are supplied in the provider dashboard. A custom sending domain needs DNS control; a hosting platform domain does not automatically grant that control. Do not send reminders until recovery/verification delivery and queue behavior have been tested with controlled staging mailboxes.

## Acceptance

Run `php artisan integrations:check --mode=test --json` on the host. It reports no credentials and performs no provider requests. Disabled OAuth providers are allowed; missing Stripe/mail settings still fail. Then complete ../docs/release-verification.md: HTTPS sessions, actual OAuth, Stripe test payments/webhooks, real mail delivery, queue/scheduler recovery, historical data review and backup/restore. Keep the PR a draft until the applicable gates pass.
