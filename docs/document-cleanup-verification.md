# Durable private document cleanup

Doctor removal now locks the doctor before the document, matching administrator-review lock order. Approved documents remain protected. For removable documents, the database transaction creates a unique private-file cleanup request and deletes the document record together. It does not remove the file before commit, so database failures preserve both the document and its file.

`documents:cleanup-files` processes up to 100 queued files by default, with a bounded `--limit` of 1–1000. It is scheduled every five minutes with overlap protection. Each item is locked, checked for remaining document references, and restricted to a safe filename within `doctor-documents/` on the private documents disk. Storage failures retain attempts, last-attempt time and a fixed error code for retry. Unsafe/referenced paths remain queued for investigation. Missing files complete successfully, including a retry after storage deletion succeeded but cleanup-record deletion failed. Older attempted items rotate behind unattempted work.

The command exits nonzero when items are deferred and logs only cleanup IDs and fixed reason codes. File paths are stored in the private cleanup table, never exposed by an API or printed by the command. Removed files remain private until cleanup runs; they are no longer downloadable through document endpoints.

Doctor metadata now exposes `file_available` and hides `file_path`, matching the administrator's existing presentation. Missing-file guidance disables downloads and explains how to supply a new copy or contact support for approved credentials. Doctor downloads use private no-store and nosniff headers. Deletion confirmation and success text explain queued cleanup.

## Verification

- Five new backend tests cover database rollback preserving files, storage failure and retry, referenced/unsafe path refusal, missing-file idempotency and limit validation, and metadata/download privacy.
- The existing deletion test now verifies both durable enqueue and eventual file cleanup. A new frontend test covers missing-file guidance and disabled downloads; the confirmation test expects queued-cleanup messaging.
- Full backend suite: **316 tests, 1779 assertions passed** on PHP 8.5.1 / SQLite.
- Full frontend suite: **126 tests passed**; production build, lint, changed PHP formatting and Git whitespace checks passed.
- Actual local browser reviewed the isolated doctor's deletion confirmation without submitting deletion. Screenshot: workspace parent `document-cleanup-confirmation.png`. Cleanup mutations and storage failures were exercised only in disposable automated test databases.

## Rollout

Apply `2026_10_07_000007_create_document_file_cleanups_table.php` before deploying the new deletion endpoint. This migration was applied only to the isolated local preview database; other unapplied preview migrations remain separate. Keep the registered scheduler supervised and give it access to the same private storage as the API. Monitor nonzero command exits, `Private document cleanup deferred` warnings and cleanup backlog age. If the scheduler is stopped, removed private files remain retained; do not promise immediate physical erasure. Investigate unsafe/referenced items deliberately rather than forcing removal. Drain or preserve the cleanup table before rolling back its migration, since dropping it discards pending cleanup paths.

This batch does not scan pre-existing orphan files or introduce upload replacement. Uploads continue to create separate credentials for review; approved records cannot be deleted by their owner. Rehearse competing approval/removal and multiple cleanup workers on the production database engine. SQLite tests do not prove real row-lock concurrency. Do not run cleanup against production until its migration, storage permissions, retention requirements and monitoring have been verified.
