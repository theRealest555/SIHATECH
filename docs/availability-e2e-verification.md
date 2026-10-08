# Automated availability and leave verification

`frontend/e2e/availability.spec.mjs` uses Chromium and the real Laravel API with the guarded disposable SQLite runner. It uses a separate synthetic doctor and patient, with one pending appointment fourteen days ahead and one confirmed appointment fifteen days ahead. These fixtures do not affect the administrator or booking specifications.

## Verified behavior

- Moving either booked weekday's schedule from 09:00–10:00 to 10:00–11:00 returns HTTP 409. Reloading availability shows the original persisted range.
- Adding leave on either booked date returns HTTP 409, and reload shows no upcoming leave.
- Extending the pending appointment's weekday to 09:00–11:00 succeeds because the existing appointment still fits.
- A second tab loaded before that extension cannot overwrite it with an older schedule revision. It receives HTTP 409; reload shows the committed extension and preserves the other weekday's original range.
- The public doctor profile offers the new 10:30 time while the occupied 09:00 time remains unavailable.
- Leave on two unbooked dates is saved and survives reload. Both inclusive endpoints show the doctor on leave with no selectable slots. The private reason is visible to the doctor and is not displayed on the public profile.
- Cancelling a removal confirmation keeps the leave. Confirming removal removes it and restores slots on the public profile.
- The doctor's upcoming appointments still contain the pending and confirmed visits after all changes.

The test uses an independent, unauthenticated browser context for the public profile and a second tab sharing the doctor's session for stale edits. It does not mock API responses. Its longer workflow has a sixty-second timeout; the other specifications keep their original limits.

## Run and result

From `frontend`, run `npm run test:e2e` after installing dependencies and Chromium. See [test setup and safeguards](admin-access-e2e-verification.md). The suite now contains three specifications, all discovered by the existing CI browser job.

On 2026-10-08, all three workflows passed locally on Windows with PHP 8.5.1 and Node 26.7.0. Frontend lint, fixture formatting and Git whitespace checks passed. The first test launch encountered an automatic approval-review timeout; the permitted retry ran successfully.

This batch changes test fixtures, browser coverage and documentation only. The previous 394 backend tests and 136 frontend tests remain the application baseline and were not rerun. No application migration or preview data change is required.

GitHub execution remains pending. These are sequential browser decisions on SQLite, not simultaneous lock contention on the production database engine. Public-page visibility checks do not replace API privacy tests. Credential verification is seeded here rather than exercised through upload and review.

Credential upload/review and MySQL booking/availability concurrency are now verified locally. See [release verification](release-verification.md) for results and remaining provider/deployed-cookie gates.
