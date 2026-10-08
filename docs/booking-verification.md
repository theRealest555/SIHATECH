# Booking flow verification — 6 October 2026

The patient searches for a verified doctor, signs in through a Sanctum cookie session, selects a live time slot, requests an appointment, and sees the database-backed pending record. The doctor confirms it through a separate session; the patient can then cancel it and see it under cancelled appointments.

## Automated evidence

- Full backend suite: **154 tests / 761 assertions pass** on PHP 8.5.1 with in-memory SQLite.
- Full frontend suite: **16 tests pass**, including ten React interaction tests for search filters/pagination, booking payload/navigation, booking conflicts, stale responses, verification requirements, request failures, patient cancellation, doctor confirmation and clinic-time display.
- Production frontend build passes. The changed booking screens, shared components, service modules and new UI tests pass ESLint. Entire frontend lint remains a separate release blocker: 110 errors / 9 warnings.
- API journey tests cover patient-profile ownership, doctor/patient visibility, pending → confirmed → cancelled status changes, slot reopening, partial overlap, leave, disabled doctor accounts, invalid filters, pagination and the general admin-list permission check.
- Timezone regression checks preserve a 09:00 appointment and its server-provided offset under the configured clinic timezone, including when client timezone data differs.

## Real browser evidence

Used the actual React app at `http://localhost:3000` and Laravel API at `http://localhost:8000` with an isolated `backend/storage/app/booking-preview.sqlite` database and synthetic preview accounts. No live application database or external provider was used.

| Boundary | Result | Evidence |
| --- | --- | --- |
| UI → public API → SQLite → doctor result | Passed | Browser displayed the seeded verified doctor with real speciality/location, with no sample doctors |
| Patient sign-in → CSRF → server session → authenticated UI | Passed | Login established the patient session and returned to the public doctor profile |
| Selected slot → booking endpoint → database write → patient list | Passed | Browser displayed the new 8 October 2026 09:00 appointment as Awaiting confirmation |
| Doctor session → confirmation endpoint → patient record | Passed | Separate doctor login displayed the patient request; confirmation refreshed the card to Confirmed |
| Patient session → cancellation endpoint → filtered list | Passed | Patient cancellation removed the record from Upcoming and displayed it under Cancelled |
| SQLite persistence | Passed | Read-only query showed appointment 1 with doctor 1, patient-profile 1, `2026-10-08 09:00:00`, status `annulé` |
| Phone navigation | Passed | At 390 × 844, Menu expanded to expose search, dashboard, appointments, profile and sign-out controls; viewport restored afterward |

The initial preview failed because `artisan serve` did not inherit this host's command-line SQLite extension setting. The final preview used PHP's development server from `backend/public` with `-d extension=pdo_sqlite`; this was a local runtime setup issue. The normal unit test command also enables that extension explicitly on this host.

Screenshots of the confirmed record, final cancelled record and mobile menu were saved in the parent task workspace. The preview servers were stopped after verification.

## Limits and next verification

This verifies the local booking path and cookie sessions with preverified accounts. It does not establish production readiness. Registration email delivery, admin approval screens, payment provider flows, production database locking, backups, queues and hosting configuration still need implementation or staging verification. Review submission remains intentionally unavailable instead of producing fake reviews.

Doctor availability and document screens were subsequently implemented and verified in [doctor-workspace-verification.md](doctor-workspace-verification.md).
