# Administrator catalogue verification

Verified locally on 7 October 2026. Changes remain local on codex/production-fixes. No production deployment or real customer records changed.

Speciality and language screens now use authenticated Laravel APIs rather than sample lists and simulated saves. They support creation, editing, paginated search, usage counts, retries and validation errors. Names use the actual nom field; specialities require a description of at most 255 characters, matching the schema. The unsupported language-code field was removed. Usage counts include soft-deleted doctor profiles.

Edits lock the reference record and compare the original field values. A stale request returns 409 instead of overwriting a different administrator's changes. Creating/editing a record and recording its audit action share one transaction. Failure to record the audit rolls back the change. Renames preserve the record ID and all doctor assignments. No reference-record deletion endpoint was introduced.

The unique-name migration inspects both tables for existing duplicate names before changing either table. Duplicate records require deliberate reconciliation; the migration does not merge, delete or reassign them. Unique indexes prevent concurrent duplicate writes using each database's collation rules. Case/accent equivalence therefore needs validation on the production engine. Rehearse migrations in maintenance/staging with an appropriate backup.

## Evidence

- Seven backend tests: real creation/update/public list visibility, audited changes, usage of soft-deleted profiles, preserved language assignments, stale conflicts, validation, database uniqueness, literal wildcard search and pagination, role restrictions, audit-failure rollback and historical duplicate preflight.
- Five frontend tests: stored-field editing with original-value checks, actual language creation, retained draft on conflict, retry/submitted search, and stale response handling.
- Full backend suite: 225 tests / 1175 assertions. Frontend: 77 tests. Production build and changed UI lint passed. Remaining full frontend lint: 47 errors / 1 warning elsewhere.
- Real browser → API → isolated SQLite: created Preview community care, renamed it to Preview community medicine, reloaded and searched successfully. Existing Cardiology remained assigned to one doctor. Created Preview test language through the actual API. Catalogue changes appear in administrator audit history with their correct target types.
- Applied the unique-name migration only to booking-preview.sqlite. Original backend .env untouched.
- Screenshots: ../../admin-specialities.png and ../../admin-languages.png. Preview records remain in the isolated database for subsequent checks.

## Remaining release work

Verify migration and concurrent edits against the production database and its actual collation. The doctor-facing public lists include saved catalogue names immediately; review naming before changes to real reference data. Plan management, reports, review submission/moderation, and remaining lint/integration/concurrency work still need completion. Retiring reference values requires a separate workflow that preserves historical doctor assignments.
