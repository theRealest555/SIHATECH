# Stale administrator status decisions

Account-status and administrator-approval updates now require `expected_status_revision`, a 64-character signed fingerprint returned as `status_revision` in the administrator user listing and user-detail response. Successful account updates return the new revision on `user`; approval updates return it on `admin`.

The fingerprint includes account identity, role, status, authentication version and administrator approval. The controller checks it after locking the target user and its administrator profile, before mutation, access revocation or audit creation. A stale decision returns HTTP 409 without changing access. Missing or malformed revisions return validation errors. A suspension/demotion followed by reactivation/reapproval still invalidates an older revision because revocation increments the authentication version. Password resets also invalidate previously loaded status decisions.

The user-management screen sends the revision captured with the confirmation. On HTTP 409 it clears the decision and offers Reload users; another decision requires reviewing the refreshed row. Other request failures keep the existing confirmation for retry. Rows without a usable revision cannot submit status changes.

## Verification

- Eight backend cases cover both endpoints' validation, stale conflicts, refreshed decisions, access returning to its original status, matching list/detail revisions and approval changes invalidating account-status decisions.
- Two new frontend cases verify conflict/reload/reconfirmation with the latest revision and disabled changes when the revision is unavailable. The existing confirmation case now checks revision propagation.
- Existing authorization, access-revocation, audit-rollback and self-deactivation tests send current revisions and continue passing.
- No preview or external account was changed. Automated backend tests use disposable SQLite; frontend interaction tests mock API responses.
- Full backend suite: **371 tests, 2139 assertions passed**. Full frontend suite: **132 tests passed**.
- Frontend lint, production build, changed PHP formatting and Git whitespace checks passed.

## Rollout and next step

Deploy frontend and API together. Existing API clients must fetch the latest revision and include it in both status endpoints; do not automatically retry a 409 with a fresh revision. No database migration is required. Authentication versions remain hidden from API responses. APP_KEY rotation invalidates loaded revisions and requires a reload.

Rehearse competing decisions on the production database engine. SQLite tests verify sequential stale decisions and rollback, not simultaneous row-lock behavior. Direct database writes that bypass application revocation/version changes do not have the same protection.

Next fix: record and display safe before/after account-status and administrator-approval transitions in audit history, so reviewers can see the exact access change.
