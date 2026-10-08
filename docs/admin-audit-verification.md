# Administrator audit history verification

Verified locally on 7 October 2026. Changes remain local on codex/production-fixes, with no production deployment or real customer data changes.

The administrator audit page now displays recorded actions from Laravel. The endpoint supports exact action and actor filters, inclusive dates, bounded page sizes and deterministic newest-first pagination. Approved, active administrators with verified email can access the authenticated route; patient, doctor and unapproved administrator access is denied by existing middleware.

Responses expose actor name/ID, timestamp, action, target label/ID and known decision details. Arbitrary legacy metadata is not returned. No invented IP addresses or system events are shown. Missing actor accounts are explicitly identified as unavailable. Responses use private/no-store caching, and the screen ignores stale filter responses, supports retries and empty results, and links from administrator navigation and dashboard.

The new migration changes the actor foreign key from cascade deletion to nullable/set-null, preserving recorded actions when an actor account is deleted. It adds indexes for timestamp, actor and action ordering. It cannot recover records previously removed by cascade deletion. Rollback retains nullable actor values rather than discarding preserved history; restoring the old cascade policy would again allow subsequent account deletion to erase associated history.

User deletion and its audit entry now share a database transaction. A failed audit save leaves the user intact. This does not introduce a new account deletion or retention policy.

## Verification

- Six backend tests cover stable pagination, safe response fields, combined actor/action/inclusive-date filters, invalid input, role restrictions, retained deleted-actor history, legacy target/metadata handling and rollback on audit failure.
- Five frontend tests cover actual record display, submitted filters and pagination reset, retry/empty state, aborted responses and HTML-like reason text rendered safely.
- Full backend suite: 218 tests / 1135 assertions. Frontend: 72 tests. Production build and changed frontend lint pass.
- Remaining whole-frontend lint: 49 errors / 3 warnings elsewhere.
- Real browser → cookie-authenticated API → isolated SQLite records: four previously recorded credential actions appear, with actual actor, target and rejection reasons. Filtering rejected_document returns exactly one record. Screenshot: ../../admin-audit-history.png.
- Applied the new migration only to the isolated booking-preview.sqlite database. Original backend .env unchanged.

## Release requirements

Back up the database and rehearse the migration against the production engine before rollout. The audit covers recorded administrator changes, not every authentication event or patient/doctor action. Deleted actor identities are not retained; define retention and identity requirements before adopting this as a regulatory audit system. Existing destructive user deletion and its cascaded business records need a separate retention review. Additional administrator reference-data, plan-management, reporting and review-moderation screens remain release work.
