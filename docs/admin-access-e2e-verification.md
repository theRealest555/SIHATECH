# Automated administrator access verification

## Coverage

`frontend/e2e/admin-access.spec.mjs` uses Chromium, the React application, the Laravel API, and a fresh SQLite database without network mocks. It signs in as an approved administrator, follows the Users link, and verifies:

- Empty and whitespace-only deactivation reasons disable confirmation.
- A second tab can complete a deactivation with a trimmed reason.
- The older tab receives HTTP 409, closes its confirmation, and offers a reload.
- Reloading allows reactivation without a deactivation reason.
- Audit history contains exactly the two valid transitions, the saved reason, and one revocation annotation. The stale reason is absent.

## Running locally

Install backend dependencies with Composer and frontend dependencies with `npm ci`. PHP must be on PATH with the Laravel extensions, including PDO SQLite; Node 24 or later is required. From `frontend`:

```sh
npx playwright install chromium --only-shell
npm run test:e2e
```

On Linux, `npx playwright install --with-deps chromium --only-shell` also installs the required system libraries. The runner enables PDO SQLite explicitly when it is installed but not loaded, as on this Windows host.

The runner creates an empty database under `backend/storage/app/e2e-runs/run-*/database.sqlite`, supplies a temporary application key and synthetic credentials, and runs migrations and fixtures there. The seed script refuses another database, a nonempty database, a non-testing environment, or cached Laravel configuration. Guard violations and migration errors exit unsuccessfully. No environment file is rewritten.

Test servers use localhost ports 4310 and 8310 and refuse reuse of existing servers. The local SMTP sink uses ports 8265/8266 and accepts only .test recipients; jobs run synchronously. The test does not exercise external providers. Databases, mail captures and failure diagnostics are ignored by Git. Run directories are retained for investigation and can be removed manually after their runs have stopped. Browser traces may include synthetic test credentials; they should not be published.

## CI

The `browser` job in `.github/workflows/verify.yml` installs PHP 8.3, Node 24, locked dependencies, and Chromium with its system libraries, then runs the same command. Failed runs upload `frontend/test-results/` for seven days. Actions are pinned to commits; the upload-artifact v7 reference was resolved from the maintainers' repository.

The existing frontend job continues to build the production bundle and run Vitest. `npm test` now targets `src` so Vitest does not collect Playwright specifications. Node test files have their own ESLint globals.

## Local results and limits

On 2026-10-08, the Chromium workflow passed on Windows with PHP 8.5.1 and Node 26.7.0. The first run's login assertion incorrectly expected Users as the default landing page; it was corrected to assert the dashboard and follow the actual Users navigation. Subsequent execution passed. An unflagged seed invocation was confirmed to exit with status 1 before migrations.

Backend PHPUnit: 394 tests / 2402 assertions passed. Frontend Vitest: 136 tests passed. Frontend lint, production build, PHP formatting, and Git whitespace checks passed. npm audit reported zero known vulnerabilities.

GitHub CI has not been executed from this local change. This covers Chromium with development servers and SQLite, not simultaneous database writes on MySQL, production cookies, other browsers, or provider delivery. The two-tab test exercises a stale revision after a committed change; it is not a simultaneous lock-contention test.

The harness now also covers [patient booking, doctor confirmation and cancellation](booking-e2e-verification.md). Production-engine concurrency verification remains a release requirement.

Configuration references: [Playwright web servers](https://playwright.dev/docs/test-webserver), [Playwright CI](https://playwright.dev/docs/ci), [Chromium headless shell installation](https://playwright.dev/docs/browsers), and [upload-artifact](https://github.com/actions/upload-artifact).
