# Administrator access workflow: real local browser

Verified on 2026-10-08 using the Codex in-app browser, Vite at localhost:3000 and Laravel at localhost:8000. Requests used the actual API and file-backed browser session; no API responses were mocked.

The preview used APP_ENV=testing and the existing isolated backend/storage/app/booking-preview.sqlite database, file sessions/cache and fake array mail. The preview servers had stopped and were restarted. Laravel's artisan serve child process did not inherit the command-line SQLite extension setting, causing the first login attempt to fail. Running the development HTTP server directly with pdo_sqlite enabled restored login; no application code or real environment file was changed.

A new synthetic patient, Access Fixture / access-workflow@preview.test (user ID 5), was created by the guarded workspace fixture helper. It has an empty patient profile and no clinical or billing history. The existing preview administrator was used; other preview accounts were not deactivated, reactivated or demoted.

## Observed flow

1. Signed in with the existing preview reviewer and searched for the synthetic account.
2. Opened deactivation confirmation. Confirm was disabled while the reason was blank and enabled after a reason was entered.
3. Kept that decision open in the first tab. In a second tab, submitted deactivation with the reason Local preview: verify account access workflow.
4. The second tab reported deactivation and displayed Inactive. Submitting the first tab's older decision returned the conflict message, cleared confirmation and offered Reload users.
5. Reloaded the first tab, reviewed Inactive, and confirmed activation without a reason. The screen reported that previously revoked access remains revoked and displayed Active.
6. Filtered real audit history to updated user status. Exactly two entries appeared: Active → Inactive with the recorded reason/revocation, and Inactive → Active. The stale decision was absent.
7. Checked the isolated fixture: final status actif, authentication version 1 (starting value 0). The fixture and its two audit records remain as local verification evidence.

Screenshots are saved in the workspace root as admin-status-conflict.png, admin-status-reactivated.png and admin-status-audit-browser.png. The browser check exercised sequential overlapping decisions in two tabs, not simultaneous database transactions. Administrator approval has no management form; its API behavior remains covered by automated tests.

## Limits and next step

No production account, external provider, real email, real payment or remote database was used. No production migration or credential change occurred. Latest automated baseline remains 394 backend tests / 2402 assertions and 136 frontend tests, with lint, build and formatting passing; application code did not change during this browser check.

Next step: automate this browser journey as an end-to-end regression test, including blank-reason blocking, two-tab stale conflict, reactivation and audit projection. Production-engine concurrency, deployed cookie domains and real provider integrations still require staging verification before release.
