# Automated booking journey verification

`frontend/e2e/booking.spec.mjs` runs against the React application, Laravel API and the disposable SQLite database prepared by `npm run test:e2e`. No API responses are mocked.

## Verified behavior

- A verified patient signs in, searches for the synthetic verified doctor and selects a date seven days ahead.
- Search reports the two expected slots; the profile carries the selected date and disables booking until a time is selected.
- Selecting 09:00 creates a pending appointment with HTTP 201 and navigates to the patient's appointment list.
- The response uses patient profile ID 2, not that account's user ID 4. The doctor's separate browser context uses independent cookies and sees the correct patient name.
- The doctor confirms the request through the UI. Reloading the patient page shows the confirmed state.
- The booked 09:00 time disappears from the public slot selection while 09:30 remains available.
- The patient cancels through the UI. The appointment leaves both accounts' upcoming lists and appears in both cancelled lists; cancellation cannot be repeated from the patient card.
- The public profile offers both slots again after cancellation.

## Fixtures and isolation

The seed script adds a synthetic doctor, speciality and booking patient after the existing administrator fixtures. The booking account is separate from the patient used by administrator access tests, so running either specification first does not change the booking account's authentication version. The doctor is seeded active, verified and email-verified; credential upload, review and approval are outside this test's scope.

The doctor has 09:00–10:00 availability on every weekday. The runner pins application time to UTC, and the test derives a future date instead of using a fixed calendar date. This keeps slots bookable without advancing Laravel's clock. UTC here is a test setting, not a deployment recommendation.

The existing guarded runner, ports 4310/8310 and failure diagnostics apply to both specifications. See [administrator test setup](admin-access-e2e-verification.md) for prerequisites and database safeguards. The tests create appointments only in a new ignored SQLite database. The open preview database and sessions are untouched.

## Result and remaining work

On 2026-10-08, both Chromium workflows passed locally on Windows with PHP 8.5.1 and Node 26.7.0. The initial fixture run failed safely because the speciality description is required; adding a synthetic description resolved it. Frontend lint, seed formatting and Git whitespace checks passed. Application code and migrations were not changed in this batch; the previous 394 backend tests and 136 frontend tests remain the baseline and were not rerun for these test-only changes.

The existing CI browser job automatically discovers both specifications. GitHub execution is still pending. SQLite and sequential decisions do not prove concurrent booking locks on MySQL. Deployed cookie domains, other browsers, mail delivery and payment providers also remain outside this test.

The harness now also covers [doctor availability and leave changes around existing appointments](availability-e2e-verification.md). Production-engine concurrency testing remains required before release.
