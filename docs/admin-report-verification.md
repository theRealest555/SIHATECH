# Administrator report verification

Verified locally on 2026-10-07 with PHP 8.5.1, Node 26.7.0, and isolated SQLite records. Changes remain local and require staging validation before production.

## Report behavior

- `/admin/reports` now displays real platform payment records and scheduled appointment activity. Approved, active, email-verified administrators can access the API and aggregate CSV exports.
- Dates default to the current month, require paired custom values, include the entire end date, and are bounded to 366 days. Invalid ranges are rejected consistently for display and export.
- Financial totals use payment record creation dates and current payment statuses. Completed gross amounts are separated by currency; failed, pending and cancelled amounts do not enter completed totals. Subscription-linked amounts use the recorded payment amount, not a plan's current price.
- Subscription creation and cancellation counts apply to the selected range. The active snapshot is explicitly current, requires an active status and current start/end window, and is independent of the historical date range.
- Appointment reports use scheduled visit dates, current statuses, zero-filled days and all 24 scheduled hours. The top ten specialities use current doctor assignments and include archived doctor profiles. They do not claim to reproduce historical speciality changes.
- Reports and exports contain aggregates, without account names, emails, payment secrets, transaction IDs or appointment identities. CSV cells neutralize spreadsheet formula prefixes and use explicit PHP CSV escaping. The old personal-data financial exporter was removed.
- Read queries run in a transaction. Rehearse report consistency with the production database's transaction isolation and concurrent writes. Each export reloads current data for the displayed range; it can differ from an earlier on-screen report after records change.
- UI supports retry, honest empty states, draft versus applied filters, export errors and aborted late responses. Switching report types clears the old result before rendering the new schema.

## Evidence

Seven database-backed report tests replace four older tests. They cover mixed currencies and decimal totals, failed payments, end-day boundaries, current active windows, scheduled versus creation dates, archived doctors, empty ranges, date limits, safe exports, formula neutralization, and authorization. Five UI tests cover real response rendering, applied dates/report types, displayed-range export requests, stale responses and retry/empty behavior.

Real local browser checks used `backend/storage/app/booking-preview.sqlite` and preview administrator #3:

1. Confirmed an honest empty payment report before adding report fixtures.
2. Added three marked isolated payment records: completed 199.10 MAD, completed 10.00 USD, failed 999 MAD. No provider call or real charge occurred.
3. Generated October 1–3 reports. Payment totals showed the two completed currencies separately and the failed record only in status counts.
4. Switched to appointments: three visits, two completed and one no-show; Cardiology count three; one appointment per day and three at 09:00.
5. An independent authenticated cookie-session HTTP check confirmed both JSON responses and both CSV exports match these fixtures and exclude identifying payment/account details. Local artifacts are `../../admin-financial-preview.csv` and `../../admin-appointments-preview.csv`.
6. The browser export action completed without an application error, but the browser automation download event timed out and no file was found in Downloads. Browser download delivery remains unconfirmed; the actual authenticated export responses and UI export-request coverage passed.

Screenshots: `../../admin-financial-report.png` and `../../admin-appointment-report.png`.

## Checks and remaining release work

- Full backend suite: 248 tests, 1386 assertions passed.
- Full frontend suite: 94 tests passed. Production build and changed-file ESLint passed. Entire frontend lint still has 43 existing errors and no warnings.
- No new schema migration or dependency change in this batch. Admin financial API responses and CSV columns now describe aggregate reporting, replacing the old mixed-currency revenue and personal payment-detail export contract; update any external consumers before rollout.
- There is no trustworthy historical paid/settled timestamp, refund ledger, processor-fee ledger or consultation billing ledger. These reports are operational summaries, not accounting or net-revenue statements. Reconcile historical payment statuses against the provider before relying on financial figures.
- Rehearse the production database engine, hosting timezones, report query performance and browser CSV download delivery in staging. Displayed timezone follows `APP_TIMEZONE`; the local isolated preview uses UTC.
- Frontend lint cleanup, live provider test-mode journeys, full staging account journeys and operational readiness remain release work.
